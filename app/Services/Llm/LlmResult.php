<?php

namespace App\Services\Llm;

/** Outcome of one full driver chat loop: the final text plus usage accounting. */
final readonly class LlmResult
{
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $toolCalls = 0,
        public int $cacheCreationTokens = 0,
        public int $cacheReadTokens = 0,
    ) {}
}
