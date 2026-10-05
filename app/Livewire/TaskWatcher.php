<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Support\TaskAlerts;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Header bell for the signed-in person's background-task notices
 * (TaskAlerts). Polls quietly; each newly finished task pops a toast on
 * whatever page is open, then stays in the bell (and the site's Alerts page)
 * until read.
 */
class TaskWatcher extends Component
{
    public bool $open = false;

    /** Toast anything finished since it was last shown (oldest first, a few at a time). */
    public function poll(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $fresh = Alert::forUser($user)->whereIn('type', TaskAlerts::TYPES)->whereNull('toasted_at')
            ->where('created_at', '>=', now()->subDay())->oldest()->limit(3)->get();
        foreach ($fresh as $a) {
            // Claim it first so two open tabs don't both toast it.
            if (Alert::whereKey($a->id)->whereNull('toasted_at')->update(['toasted_at' => now()]) === 0) {
                continue;
            }
            $this->dispatch('toast', level: $a->level === 'error' ? 'error' : 'success', title: $a->title,
                message: (string) $a->body, link: $a->link, timeout: 9000);
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAllRead(): void
    {
        Alert::forUser(Auth::user())->whereIn('type', TaskAlerts::TYPES)->whereNull('read_at')
            ->update(['read_at' => now(), 'toasted_at' => now()]);
    }

    public function openAlert(string $id)
    {
        $a = Alert::forUser(Auth::user())->findOrFail($id);
        $a->update(['read_at' => $a->read_at ?? now(), 'toasted_at' => $a->toasted_at ?? now()]);

        return $a->link ? $this->redirect($a->link) : null;
    }

    public function render()
    {
        $user = Auth::user();
        $base = $user ? Alert::forUser($user)->whereIn('type', TaskAlerts::TYPES) : null;

        return view('livewire.task-watcher', [
            'unread' => $base ? (clone $base)->whereNull('read_at')->count() : 0,
            'recent' => $this->open && $base ? (clone $base)->with('site:id,name')->latest()->limit(8)->get() : collect(),
        ]);
    }
}
