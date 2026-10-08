<?php

namespace App\Livewire;

use App\Models\PlatformTestimonial;
use App\Support\ConfigOverlay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Platform admin › Testimonials: the reviews of Olux shown on the landing
 * page. Public submissions (/testimonials/new) arrive pending; admins
 * publish, hide, edit, reorder, delete or add their own, and choose how
 * many the landing page shows.
 */
class PlatformTestimonialsPage extends Component
{
    /** all (default) | pending | published | hidden */
    #[Url]
    public string $tab = 'all';

    /** Testimonial id being edited; '' = new; null = drawer closed. */
    public ?string $editing = null;

    public array $form = [];

    public int $landingCount = 5;

    public function mount(): void
    {
        $this->guard();
        $this->landingCount = (int) config('landing.testimonials_count', 5);
        if (! in_array($this->tab, ['pending', 'published', 'hidden', 'all'], true)) {
            $this->tab = 'all';
        }
    }

    /** Every action re-checks: only super admins manage testimonials. */
    private function guard(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['pending', 'published', 'hidden', 'all'], true) ? $tab : 'all';
    }

    public function create(): void
    {
        $this->guard();
        $this->editing = '';
        $this->form = ['name' => '', 'role' => '', 'quote' => '', 'rating' => '5', 'email' => '', 'status' => 'published'];
        $this->resetErrorBag();
    }

    public function edit(string $id): void
    {
        $this->guard();
        $t = PlatformTestimonial::findOrFail($id);
        $this->editing = $t->id;
        $this->form = [
            'name' => $t->name, 'role' => (string) $t->role, 'quote' => $t->quote,
            'rating' => (string) ($t->rating ?? ''), 'email' => (string) $t->email, 'status' => $t->status,
        ];
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->editing = null;
    }

    public function save(): void
    {
        $this->guard();
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:80'],
            'form.role' => ['nullable', 'string', 'max:120'],
            'form.quote' => ['required', 'string', 'min:10', 'max:600'],
            'form.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'form.email' => ['nullable', 'email', 'max:160'],
            'form.status' => ['required', 'in:'.implode(',', PlatformTestimonial::STATUSES)],
        ], [], ['form.quote' => 'testimonial'])['form'];

        $attrs = [
            'name' => trim($data['name']),
            'role' => trim((string) $data['role']) ?: null,
            'quote' => trim($data['quote']),
            'rating' => $data['rating'] === '' || $data['rating'] === null ? null : (int) $data['rating'],
            'email' => trim((string) $data['email']) ?: null,
            'status' => $data['status'],
        ];

        if ($this->editing) {
            $t = PlatformTestimonial::findOrFail($this->editing);
            $t->update($attrs + ['published_at' => $attrs['status'] === 'published' ? ($t->published_at ?? now()) : $t->published_at]);
        } else {
            PlatformTestimonial::create($attrs + [
                'source' => 'admin',
                'position' => (int) PlatformTestimonial::max('position') + 1,
                'published_at' => $attrs['status'] === 'published' ? now() : null,
            ]);
        }
        $this->editing = null;
        $this->dispatch('toast', level: 'success', title: 'Testimonial saved',
            message: $attrs['status'] === 'published' ? 'It shows on the landing page (within your top '.$this->landingCount.').' : 'Saved as '.$attrs['status'].'.');
    }

    public function setStatus(string $id, string $status): void
    {
        $this->guard();
        abort_unless(in_array($status, PlatformTestimonial::STATUSES, true), 422);
        $t = PlatformTestimonial::findOrFail($id);
        $t->update(['status' => $status, 'published_at' => $status === 'published' ? ($t->published_at ?? now()) : $t->published_at]);
        $this->dispatch('toast', level: 'success', title: match ($status) {
            'published' => 'Published', 'hidden' => 'Hidden', default => 'Moved to pending',
        }, message: $t->name.'’s testimonial '.($status === 'published' ? 'is on the landing page.' : 'is off the landing page.'));
    }

    /** Move a published testimonial up/down the landing order. */
    public function move(string $id, int $dir): void
    {
        $this->guard();
        $list = PlatformTestimonial::live()->get()->values();
        $i = $list->search(fn ($t) => $t->id === $id);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || ! isset($list[$j])) {
            return;
        }
        DB::transaction(function () use ($list, $i, $j) {
            $ordered = $list->all();
            [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
            foreach ($ordered as $pos => $t) {
                $t->update(['position' => $pos + 1]);
            }
        });
    }

    public function delete(string $id): void
    {
        $this->guard();
        PlatformTestimonial::whereKey($id)->delete();
        if ($this->editing === $id) {
            $this->editing = null;
        }
        $this->dispatch('toast', level: 'success', title: 'Deleted', message: 'The testimonial is gone.');
    }

    public function saveLandingCount(): void
    {
        $this->guard();
        $this->validate(['landingCount' => ['required', 'integer', 'min:1', 'max:20']]);
        ConfigOverlay::set('landing.testimonials_count', $this->landingCount);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: "The landing page shows up to {$this->landingCount} testimonials.");
    }

    public function render()
    {
        $counts = PlatformTestimonial::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $rows = match ($this->tab) {
            'published' => PlatformTestimonial::live()->get(),
            'pending', 'hidden' => PlatformTestimonial::where('status', $this->tab)->latest()->get(),
            // Everything: waiting for approval first, then live (landing order), then hidden.
            default => PlatformTestimonial::query()
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")
                ->orderByRaw("CASE WHEN status = 'published' THEN position ELSE 0 END")
                ->latest()->get(),
        };

        return view('livewire.platform-testimonials-page', [
            'rows' => $rows,
            'counts' => $counts,
            'stats' => [
                'published' => (int) ($counts['published'] ?? 0),
                'pending' => (int) ($counts['pending'] ?? 0),
                'hidden' => (int) ($counts['hidden'] ?? 0),
                'avg' => round((float) PlatformTestimonial::where('status', 'published')->whereNotNull('rating')->avg('rating'), 1),
                'week' => PlatformTestimonial::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'onLanding' => PlatformTestimonial::forLanding()->pluck('id')->all(),
        ]);
    }
}
