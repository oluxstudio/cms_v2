<?php

namespace App\Services\Llm;

use Anthropic\Client;
use App\Contracts\LlmDriverInterface;

/**
 * LLM driver backed by the Anthropic Claude API.
 *
 * Wraps the existing tool-use loop that was previously inlined in SiteAgent.
 * Tools are passed in Anthropic's native schema format — no conversion needed.
 */
class AnthropicDriver implements LlmDriverInterface
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
        // Stable system block — eligible for Anthropic prompt caching.
        $system = [[
            'type' => 'text',
            'text' => $systemPrompt,
            'cacheControl' => ['type' => 'ephemeral'],
        ]];

        // Convert simple role/content turns to Anthropic message format.
        $apiMessages = array_map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $m['content'],
        ], $messages);

        $finalText = '';
        $toolsCalledAll = [];

        for ($i = 0; $i < 12; $i++) {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 8096,
                system: $system,
                tools: $tools,
                thinking: ['type' => 'disabled'],
                messages: $apiMessages,
            );

            // Collect text + execute any tool calls in this response.
            $toolResults = [];
            $toolsThisTurn = [];

            foreach ($response->content as $block) {
                if ($block->type === 'text') {
                    $finalText .= $block->text;
                } elseif ($block->type === 'tool_use') {
                    $result = $executeTool($block->name, (array) $block->input);
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'toolUseID' => $block->id,
                        'content' => $result['message'],
                        'isError' => ! $result['ok'],
                    ];
                    $toolsThisTurn[] = $block->name;
                    $toolsCalledAll[] = $block->name;
                }
            }

            // Natural stop — check whether we need a content-creation nudge.
            if ($response->stopReason !== 'tool_use') {
                $createdPage = in_array('create_page', $toolsCalledAll, true);
                $createdComponent = in_array('create_component', $toolsCalledAll, true);

                if ($createdPage && ! $createdComponent && $i < 10) {
                    $apiMessages[] = ['role' => 'assistant', 'content' => $response->content ?: [['type' => 'text', 'text' => 'Done.']]];
                    $apiMessages[] = ['role' => 'user',      'content' => 'You created the page but have not added any components or content nodes yet. Please continue: call create_component and then add_node multiple times to populate the page with real, topic-appropriate content.'];
                    $finalText = '';

                    continue;
                }

                break;
            }

            // Feed tool results back and loop.
            $apiMessages[] = ['role' => 'assistant', 'content' => $response->content];
            $apiMessages[] = ['role' => 'user',      'content' => $toolResults];
            $finalText = '';
        }

        return trim($finalText) ?: 'Done.';
    }

    public function prefersCompactPrompt(): bool
    {
        return false; // Anthropic Claude handles large context easily
    }
}
