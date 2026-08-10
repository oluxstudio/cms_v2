<?php

namespace App\Providers;

use Anthropic\Anthropic;
use App\Contracts\LlmDriverInterface;
use App\Services\Llm\AnthropicDriver;
use App\Services\Llm\DeepSeekDriver;
use App\Services\Llm\OllamaDriver;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use OpenAI;

/**
 * Binds the correct LlmDriverInterface implementation based on the LLM_DRIVER env var.
 *
 * Supported drivers:
 *   anthropic  — Anthropic Claude API (requires ANTHROPIC_API_KEY)
 *   deepseek   — DeepSeek cloud API (requires DEEPSEEK_API_KEY, OpenAI-compatible)
 *   ollama     — Local Ollama instance (OpenAI-compatible, no key required)
 *
 * .env examples:
 *   LLM_DRIVER=deepseek
 *   DEEPSEEK_API_KEY=sk-xxxxxxx
 *   DEEPSEEK_MODEL=deepseek-chat
 *
 *   LLM_DRIVER=anthropic
 *   ANTHROPIC_API_KEY=sk-ant-xxxxxxx
 *
 *   LLM_DRIVER=ollama
 *   OLLAMA_BASE_URL=http://localhost:11434/v1
 *   OLLAMA_MODEL=cms-operator
 */
class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmDriverInterface::class, function () {
            $driver = config('services.llm.driver', 'anthropic');

            return match ($driver) {
                'deepseek' => new DeepSeekDriver(
                    client: OpenAI::factory()
                        ->withBaseUri('https://api.deepseek.com/v1')
                        ->withApiKey(config('services.deepseek.key', ''))
                        ->withHttpClient(new Client(['timeout' => 120, 'connect_timeout' => 10]))
                        ->make(),
                    model: config('services.deepseek.model', 'deepseek-chat'),
                ),

                'ollama' => new OllamaDriver(
                    client: OpenAI::factory()
                        ->withBaseUri(config('services.ollama.base_url', 'http://localhost:11434/v1'))
                        ->withApiKey('ollama') // Ollama ignores the key; SDK requires one
                        ->withHttpClient(new Client(['timeout' => 300, 'connect_timeout' => 10]))
                        ->make(),
                    model: config('services.ollama.model', 'qwen3:8b'),
                ),

                default => new AnthropicDriver(
                    client: Anthropic::client(config('services.anthropic.key', '')),
                    model: config('services.anthropic.model', 'claude-opus-4-5'),
                ),
            };
        });
    }
}
