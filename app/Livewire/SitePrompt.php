<?php

namespace App\Livewire;

use App\Models\Site;
use App\Services\SiteAgent;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SitePrompt extends Component
{
    public string $siteId;

    public string $input = '';

    public array $messages = [];

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        $this->messages = session("chat:{$siteId}", []);
    }

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    public function send(): void
    {
        $text = trim($this->input);
        if ($text === '') {
            return;
        }

        $this->input = '';

        // Per-site rate limit: the assistant is metered, not hammerable.
        $limiterKey = 'ai:'.$this->siteId;
        $perHour = (int) config('services.llm.per_hour', 30);
        if (RateLimiter::tooManyAttempts($limiterKey, $perHour)) {
            $this->messages[] = ['role' => 'user', 'text' => $text];
            $this->messages[] = ['role' => 'assistant', 'ok' => false,
                'text' => 'The assistant is cooling down — this site has used its '.$perHour.' AI requests for the hour. Try again shortly.'];
            $this->dispatch('chat-updated');

            return;
        }
        RateLimiter::hit($limiterKey, 3600);

        // Add user message
        $this->messages[] = ['role' => 'user', 'text' => $text];

        // Build history for context (last 10 turns)
        $history = array_slice($this->messages, -11, -1);

        // Call the AI
        if (SiteAgent::configured()) {
            $result = app(SiteAgent::class)->ask(
                $this->site,
                auth()->user(),
                $text,
                $history,
            );
            $reply = $result['text'];
            $ok = $result['ok'];
            $built = $result['built'] ?? false;
            $page = $result['page'] ?? null;
        } else {
            $reply = 'No AI driver configured. Add DEEPSEEK_API_KEY and set LLM_DRIVER=deepseek in your .env file.';
            $ok = false;
            $built = false;
            $page = null;
        }

        // Add AI response
        $this->messages[] = ['role' => 'assistant', 'text' => $reply, 'ok' => $ok];

        // Keep last 30 messages and persist
        $this->messages = array_slice($this->messages, -30);
        session()->put("chat:{$this->siteId}", $this->messages);

        $this->dispatch('chat-updated');

        // When the agent actually built/changed the app, land on the Build canvas —
        // clarifying-question turns (no writes) stay in the chat so the user can answer.
        if ($ok && $built) {
            $this->redirect(route('blocks', ['siteID' => $this->site->name]), navigate: true);

            return;
        }

        $this->dispatch('toast',
            level: $ok ? 'success' : 'error',
            title: $ok ? 'Done' : 'Error',
            message: mb_substr($reply, 0, 120),
        );
    }

    public function clearChat(): void
    {
        $this->messages = [];
        session()->forget("chat:{$this->siteId}");
    }

    public function render()
    {
        return view('livewire.site-prompt');
    }
}
