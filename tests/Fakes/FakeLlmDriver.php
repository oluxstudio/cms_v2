<?php

namespace Tests\Fakes;

use App\Contracts\LlmDriverInterface;
use App\Services\Llm\LlmResult;

/** Scripted driver: optionally calls one tool, then answers with fixed usage. */
class FakeLlmDriver implements LlmDriverInterface
{
    public array $seenSystemPrompts = [];

    public function __construct(
        public string $answer = 'Here you go.',
        public ?string $callTool = null,
        public array $toolInput = [],
    ) {}

    public function chat(string $systemPrompt, array $messages, array $tools, callable $executeTool): LlmResult
    {
        $this->seenSystemPrompts[] = $systemPrompt;
        $calls = 0;
        if ($this->callTool !== null) {
            $executeTool($this->callTool, $this->toolInput);
            $calls = 1;
        }

        return new LlmResult($this->answer, 111, 42, $calls);
    }

    public function prefersCompactPrompt(): bool
    {
        return true;
    }
}
