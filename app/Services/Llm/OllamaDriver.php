<?php

namespace App\Services\Llm;

use App\Contracts\LlmDriverInterface;
use OpenAI\Client;

/**
 * LLM driver backed by a local Ollama instance (OpenAI-compatible API).
 *
 * Uses STREAMING mode because Ollama's non-streaming endpoint can hang
 * indefinitely on some builds. Chunks are accumulated into the final text.
 *
 * Model-specific behaviour:
 *   - Qwen3:      appends /no_think to suppress reasoning blocks; strips <think>
 *   - DeepSeek-R1: always reasons; /no_think not supported — strips <think> only
 *   - cms-operator: custom Modelfile-trained model (recommended); no special handling needed
 *
 * Recommended models (in order of preference):
 *   - cms-operator        (custom, baked CMS knowledge — build with: ollama create cms-operator -f Modelfile.cms)
 *   - deepseek-r1:7b      (strong tool-chaining, ~4.9 GB)
 *   - qwen3:8b            (good alternative)
 */
class OllamaDriver implements LlmDriverInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {}

    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools,
        callable $executeTool,
    ): string {
        $openAiTools = $this->convertTools($tools);

        // Qwen3 supports /no_think to suppress verbose reasoning. DeepSeek-R1 always reasons (no equivalent flag).
        $systemContent = $this->isQwen3() ? $systemPrompt."\n/no_think" : $systemPrompt;

        $apiMessages = array_merge(
            [['role' => 'system', 'content' => $systemContent]],
            array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $messages),
        );

        $finalText = '';
        $toolsCalledAll = [];

        for ($i = 0; $i < 16; $i++) {
            [$finishReason, $content, $toolCalls] = $this->streamChat($apiMessages, $openAiTools);

            // ── Tool call turn ─────────────────────────────────────────────
            if ($finishReason === 'tool_calls' && ! empty($toolCalls)) {
                $apiMessages[] = [
                    'role' => 'assistant',
                    'content' => $content ?? '',
                    'tool_calls' => array_map(fn ($tc) => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['function']['name'],
                            'arguments' => $tc['function']['arguments'],
                        ],
                    ], $toolCalls),
                ];

                foreach ($toolCalls as $toolCall) {
                    $args = json_decode($toolCall['function']['arguments'], true) ?? [];
                    $result = $executeTool($toolCall['function']['name'], $args);

                    $apiMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $toolCall['function']['name'],
                        'content' => $result['message'],
                    ];
                    $toolsCalledAll[] = $toolCall['function']['name'];
                }

                $finalText = '';

                continue;
            }

            // ── Normal stop ────────────────────────────────────────────────
            $finalText = $this->usesThinkBlocks()
                ? $this->stripThinkBlocks($content ?? '')
                : ($content ?? '');

            // Nudge: if create_page ran but no component was created, keep going.
            $createdPage = in_array('create_page', $toolsCalledAll, true);
            $createdComponent = in_array('create_component', $toolsCalledAll, true);

            if ($createdPage && ! $createdComponent && $i < 14) {
                $apiMessages[] = ['role' => 'assistant', 'content' => $finalText ?: 'Done.'];
                $apiMessages[] = [
                    'role' => 'user',
                    'content' => 'You created the page but have not added any components or content nodes yet. '
                        .'Continue now: call create_component to add a named section, '
                        .'then call add_node multiple times to fill it with real, topic-appropriate content. '
                        .'Do not stop until the page has meaningful content.',
                ];
                $finalText = '';

                continue;
            }

            break;
        }

        return trim($finalText) ?: 'Done.';
    }

    // ── Streaming helper ───────────────────────────────────────────────────

    /**
     * Send a streaming chat request and accumulate the result.
     *
     * Returns [finish_reason, accumulated_text, tool_calls_array].
     * Tool calls are assembled from streaming deltas (index-keyed).
     */
    private function streamChat(array $messages, array $tools): array
    {
        $stream = $this->client->chat()->createStreamed([
            'model' => $this->model,
            'messages' => $messages,
            'tools' => $tools,
            'options' => ['num_ctx' => 8192],  // override default 4096 context window
        ]);

        $text = '';
        $finishReason = 'stop';
        $toolMap = []; // index => ['id','function'=>['name','arguments']]

        foreach ($stream as $response) {
            $choice = $response->choices[0] ?? null;
            if (! $choice) {
                continue;
            }

            if ($choice->finishReason !== null) {
                $finishReason = $choice->finishReason;
            }

            $delta = $choice->delta;

            // Accumulate visible text content (ignore the "reasoning" field — Qwen3 thinks out loud).
            if (isset($delta->content) && $delta->content !== null) {
                $text .= $delta->content;
            }

            // Accumulate tool call deltas (streamed in pieces by index).
            if (! empty($delta->toolCalls)) {
                foreach ($delta->toolCalls as $tc) {
                    $idx = $tc->index ?? 0;
                    if (! isset($toolMap[$idx])) {
                        $toolMap[$idx] = ['id' => '', 'function' => ['name' => '', 'arguments' => '']];
                    }
                    if (! empty($tc->id)) {
                        $toolMap[$idx]['id'] = $tc->id;
                    }
                    if (isset($tc->function->name) && $tc->function->name !== null) {
                        $toolMap[$idx]['function']['name'] .= $tc->function->name;
                    }
                    if (isset($tc->function->arguments) && $tc->function->arguments !== null) {
                        $toolMap[$idx]['function']['arguments'] .= $tc->function->arguments;
                    }
                }
            }
        }

        $toolCalls = array_values($toolMap);

        return [$finishReason, $text, $toolCalls];
    }

    public function prefersCompactPrompt(): bool
    {
        return true; // local models have limited context windows
    }

    // ── Tool schema conversion ─────────────────────────────────────────────

    /**
     * Convert Anthropic tool definitions to OpenAI function-calling format.
     *
     * Anthropic:  ['name'=>..., 'description'=>..., 'input_schema'=>{...}]
     * OpenAI:     ['type'=>'function', 'function'=>['name','description','parameters']]
     *
     * We round-trip through JSON to guarantee that empty PHP arrays that should be
     * objects (e.g. `properties: {}`) stay as objects — PHP's json_encode would
     * otherwise emit `[]` for an empty array, which Ollama rejects.
     */
    private function convertTools(array $anthropicTools): array
    {
        $tools = array_map(fn ($t) => [
            'type' => 'function',
            'function' => [
                'name' => $t['name'],
                'description' => $t['description'],
                'parameters' => $t['input_schema'],
            ],
        ], $anthropicTools);

        // Decode as associative=false so objects stay as stdClass → re-encode ensures {} not [].
        return json_decode(json_encode($tools), false);
    }

    /**
     * Strip <think>...</think> reasoning blocks.
     * Both Qwen3 and DeepSeek-R1 emit these; the cms-operator model suppresses them via Modelfile.
     */
    private function stripThinkBlocks(string $text): string
    {
        return trim(preg_replace('/<think>.*?<\/think>/s', '', $text));
    }

    /**
     * True for models that emit <think>...</think> blocks (Qwen3, DeepSeek-R1).
     * Used to decide whether to run stripThinkBlocks() on output.
     */
    private function usesThinkBlocks(): bool
    {
        $m = strtolower($this->model);

        return str_contains($m, 'qwen3') || str_contains($m, 'deepseek-r1');
    }

    /**
     * True for Qwen3-based models — the only ones that support the /no_think suffix.
     * Includes cms-operator (which is built from qwen3:8b).
     * DeepSeek-R1 always reasons; /no_think is not supported there.
     */
    private function isQwen3(): bool
    {
        $m = strtolower($this->model);

        return str_contains($m, 'qwen3') || $m === 'cms-operator' || str_starts_with($m, 'cms-operator');
    }
}
