<?php

namespace App\Contracts;

/**
 * Contract for LLM provider drivers.
 *
 * Each driver wraps one provider's API (Anthropic, Ollama, etc.) and exposes a
 * single method that runs the full tool-use loop and returns the final text.
 *
 * Tool definitions are passed in Anthropic's canonical format; drivers are
 * responsible for converting to their own wire format if needed.
 */
interface LlmDriverInterface
{
    /**
     * Run a prompt against the model, executing any tool calls via the callback,
     * and return the final assistant text.
     *
     * @param  string  $systemPrompt  Full assembled system prompt
     * @param  array  $messages  Prior turns: [['role' => 'user|assistant', 'content' => '...'], ...]
     * @param  array  $tools  Anthropic-format tool definitions from SiteTools::definitions()
     * @param  callable  $executeTool  fn(string $name, array $input): array{ok:bool,message:string}
     * @return string Final assistant text after all tool-use rounds complete
     */
    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools,
        callable $executeTool,
    ): string;

    /**
     * Whether this driver prefers a compact system prompt.
     *
     * Local models (Ollama) have limited context windows; returning true
     * tells SiteAgent to use a shorter prompt without the large reference files.
     */
    public function prefersCompactPrompt(): bool;
}
