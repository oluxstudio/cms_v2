<?php

namespace App\Livewire;

use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Polls admin — create quick polls, manage their options, watch live results.
 * Visitors vote through the public polls API from the client site.
 */
class PollsPage extends Component
{
    public Site $site;

    // ── Create (right rail) ──
    public string $newQuestion = '';

    // ── Editor (main column) ──
    #[Url(as: 'poll')]
    public ?string $selectedId = null;

    public string $pQuestion = '';

    public bool $pMultiple = false;

    public string $pEndsAt = '';           // yyyy-mm-dd or ''

    public string $newOption = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;

        // Deep link: /{site}/polls?poll={id} opens that poll's editor directly.
        if ($this->selectedId) {
            $this->canManage && Poll::where('site_id', $site->id)->whereKey($this->selectedId)->exists()
                ? $this->select($this->selectedId)
                : $this->closeEditor();
        }
    }

    private function guardManage(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'polls.manage'), 403);
    }

    public function getCanManageProperty(): bool
    {
        return $this->site->allows(Auth::user(), 'polls.manage');
    }

    public function getPollsProperty()
    {
        return Poll::where('site_id', $this->site->id)
            ->withCount(['votes', 'options'])->orderByDesc('created_at')->get();
    }

    /** Top 7 by distinct voters (ties break on raw votes). */
    public function getPopularPollsProperty()
    {
        return $this->polls->sortByDesc(fn ($p) => [$p->voterCount(), $p->votes_count])->take(7)->values();
    }

    /** Top 7 newest. */
    public function getLatestPollsProperty()
    {
        return $this->polls->sortByDesc('created_at')->take(7)->values();
    }

    public function getSelectedProperty(): ?Poll
    {
        return $this->selectedId ? Poll::where('site_id', $this->site->id)->find($this->selectedId) : null;
    }

    /** Live results for the open editor. */
    public function getResultsProperty(): array
    {
        return $this->selected?->results() ?? [];
    }

    /** Left-rail tiles. */
    public function getStatsProperty(): array
    {
        $polls = $this->polls;

        return [
            'polls' => $polls->count(),
            'open' => $polls->filter(fn ($p) => $p->acceptsVotes())->count(),
            'votes' => (int) $polls->sum('votes_count'),
            'week' => PollVote::where('site_id', $this->site->id)
                ->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    public function getRecentVotesProperty()
    {
        return PollVote::where('site_id', $this->site->id)
            ->with(['poll:id,question', 'option:id,label'])
            ->latest('created_at')->limit(20)->get();
    }

    /**
     * "Open poll page": scaffold a page INSIDE the site's template (poll
     * block only, chrome wraps it, not in the menu) and open it. The same
     * "Poll" component can also be attached to ANY page from the Pages list.
     */
    public function openPollPage(?string $pollId = null): void
    {
        if (! $this->site->templatePreviewUrl()) {
            $this->dispatch('toast', level: 'error', title: 'No template', message: 'This site has no rendered template to host the poll page.');

            return;
        }
        try {
            if (! $this->site->pages()->where('url', '/polls')->exists()) {
                app(\App\Services\TemplateScaffolder::class)->applyPages($this->site, [[
                    'name' => 'Polls',
                    'url' => '/polls',
                    'keywords' => 'polls, vote',
                    'blocks' => [['type' => 'app:'.$this->site->renderTemplateKey().':poll', 'name' => 'Poll', 'nodes' => [['label' => 'Poll', 'type' => 'text', 'value' => '', 'order' => 0, 'description' => 'Poll slug to pin (blank = all open polls)']]]],
                ]]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $url = $this->site->templatePreviewUrl('/polls') ?: $this->site->templatePreviewUrl();

        // Deep-link ONE poll: the poll page filters itself to ?poll={slug}.
        if ($pollId && ($poll = Poll::where('site_id', $this->site->id)->find($pollId))) {
            $url .= (str_contains($url, '?') ? '&' : '?').'poll='.urlencode($poll->slug);
        }

        $this->redirect($url);
    }

    // ═══ Lifecycle ═══

    public function createPoll(): void
    {
        $this->guardManage();
        $this->validate(['newQuestion' => ['required', 'string', 'max:200']]);

        $question = trim($this->newQuestion);
        $poll = Poll::create([
            'site_id' => $this->site->id,
            'question' => $question,
            'slug' => Poll::slugFor($this->site->id, $question),
        ]);

        $this->reset('newQuestion');
        $this->dispatch('toast', level: 'success', title: 'Poll created', message: 'Now add at least two options.');
        $this->select($poll->id);
    }

    public function select(string $id): void
    {
        $this->guardManage();
        $poll = Poll::where('site_id', $this->site->id)->findOrFail($id);
        $this->selectedId = $poll->id;
        $this->pQuestion = $poll->question;
        $this->pMultiple = $poll->multiple;
        $this->pEndsAt = $poll->ends_at?->format('Y-m-d') ?? '';
        $this->reset(['newOption', 'errorMessage']);
    }

    public function closeEditor(): void
    {
        $this->reset(['selectedId', 'pQuestion', 'pMultiple', 'pEndsAt', 'newOption', 'errorMessage']);
    }

    public function savePoll(): void
    {
        $this->guardManage();
        abort_unless($this->selected !== null, 404);
        $this->validate([
            'pQuestion' => ['required', 'string', 'max:200'],
            'pEndsAt' => ['nullable', 'date'],
        ]);
        $this->selected->update([
            'question' => trim($this->pQuestion),
            'multiple' => $this->pMultiple,
            'ends_at' => $this->pEndsAt !== '' ? $this->pEndsAt.' 23:59:59' : null,
        ]);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Poll updated.');
    }

    public function addOption(): void
    {
        $this->guardManage();
        abort_unless($this->selected !== null, 404);
        $label = trim($this->newOption);
        if ($label === '') {
            return;
        }
        if ($this->selected->options()->count() >= 12) {
            $this->errorMessage = 'A poll can hold at most 12 options.';

            return;
        }
        $this->selected->options()->create([
            'site_id' => $this->site->id,
            'label' => $label,
            'sort' => $this->selected->options()->count(),
        ]);
        $this->reset('newOption');
    }

    /** Deleting an option discards its votes with it. */
    public function deleteOption(string $id): void
    {
        $this->guardManage();
        $option = $this->selected?->options()->findOrFail($id);
        $option?->votes()->delete();
        $option?->delete();
    }

    public function toggleOpen(string $id): void
    {
        $this->guardManage();
        $poll = Poll::where('site_id', $this->site->id)->findOrFail($id);
        $poll->update(['is_open' => ! $poll->is_open]);
    }

    public function deletePoll(string $id): void
    {
        $this->guardManage();
        $poll = Poll::where('site_id', $this->site->id)->findOrFail($id);
        $poll->votes()->delete();
        $poll->options()->delete();
        $poll->delete();
        if ($this->selectedId === $id) {
            $this->closeEditor();
        }
        $this->dispatch('toast', level: 'success', title: 'Poll deleted', message: $poll->question);
    }

    public function render()
    {
        return view('livewire.polls-page');
    }
}
