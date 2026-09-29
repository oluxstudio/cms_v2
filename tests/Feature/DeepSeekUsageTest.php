<?php

use App\Services\Llm\DeepSeekDriver;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use OpenAI\Testing\ClientFake;

test('streamed replies report their token usage so AI caps and costs work', function () {
    $chunk = fn (array $data) => 'data: '.json_encode($data)."\n";
    $base = ['id' => 'c1', 'object' => 'chat.completion.chunk', 'created' => 1, 'model' => 'deepseek-chat'];
    $body = $chunk($base + ['choices' => [['index' => 0, 'delta' => ['role' => 'assistant', 'content' => 'Hello'], 'finish_reason' => null]]])
        .$chunk($base + ['choices' => [['index' => 0, 'delta' => ['content' => ' there'], 'finish_reason' => 'stop']]])
        // Final usage-only chunk (stream_options.include_usage): no choices.
        .$chunk($base + ['choices' => [], 'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 85, 'total_tokens' => 1285]])
        ."data: [DONE]\n";
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $body);
    rewind($stream);

    $client = new ClientFake([CreateStreamedResponse::fake($stream)]);
    $result = (new DeepSeekDriver($client, 'deepseek-chat'))->chat('system', [['role' => 'user', 'content' => 'Hi']], [], fn () => []);

    expect($result->text)->toBe('Hello there')
        ->and($result->inputTokens)->toBe(1200)
        ->and($result->outputTokens)->toBe(85);

    $client->assertSent(Chat::class, fn (string $method, array $params) => ($params['stream_options']['include_usage'] ?? false) === true);
});
