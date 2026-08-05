<?php

namespace App\Livewire;

use App\Models\Page;
use App\Models\Site;
use App\Services\BlockAgent;
use App\Services\SiteAgent;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Polux chat pane inside the Blocks editor — the AI client of jigsaw parity.
 * It shares live context with the editor (current page, selected block) via
 * browser events, mutates through the same BlockTreeService, and pings the
 * editor to refresh after every mutating turn.
 */
class BlockAssistant extends Component
{
    public int $siteId;

    public ?int $pageId = null;

    public ?string $selectedId = null;

    public string $draft = '';

    public bool $busy = false;

    /** @var array<int,array{role:string,text:string}> */
    public array $messages = [];

    public function mount(int $siteId, ?int $pageId = null): void
    {
        $this->siteId = $siteId;
        $this->pageId = $pageId;
    }

    #[On('bk-page-changed')]
    public function onPageChanged(int $pageId): void
    {
        $this->pageId = $pageId;
        $this->selectedId = null;
    }

    #[On('bk-selected')]
    public function onSelected(?string $blockId = null): void
    {
        $this->selectedId = $blockId;
    }

    /** Two-phase send: paint the user bubble instantly, then run the model. */
    public function send(): void
    {
        $prompt = trim($this->draft);
        if ($prompt === '' || $this->busy) {
            return;
        }
        $this->messages[] = ['role' => 'user', 'text' => $prompt];
        $this->draft = '';
        $this->busy = true;
        $this->dispatch('bk-chat-run');
    }

    #[On('bk-chat-run')]
    public function run(): void
    {
        if (! $this->busy) {
            return;
        }
        $site = Site::findOrFail($this->siteId);
        $page = $this->pageId ? Page::where('site_id', $site->id)->find($this->pageId) : null;
        $last = collect($this->messages)->last();

        if (! $page || ! $last || $last['role'] !== 'user') {
            $this->busy = false;

            return;
        }

        if (! SiteAgent::configured()) {
            $this->messages[] = ['role' => 'assistant', 'text' => 'No AI model is configured — set DEEPSEEK_API_KEY (or another LLM driver) in the environment.'];
            $this->busy = false;

            return;
        }

        $result = app(BlockAgent::class)->ask(
            $site,
            auth()->user(),
            $page,
            $this->selectedId,
            $last['text'],
            array_slice($this->messages, 0, -1),
        );

        $this->messages[] = ['role' => 'assistant', 'text' => $result['text']];
        $this->busy = false;

        if ($result['mutated']) {
            // One event refreshes everything: editor layers, canvas, preview iframe.
            $this->dispatch('bk-mutated');
        }
    }

    public function render()
    {
        return view('livewire.block-assistant');
    }
}
