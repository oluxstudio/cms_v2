<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class AlertsPage extends Component
{
    public int $siteId;

    #[Url(as: 'cat')]
    public string $filter = 'all';   // all | unread | type:* | level:*

    public ?int $expanded = null;

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    #[Computed]
    public function alerts()
    {
        $q = Alert::visibleTo($this->site, Auth::user())->latest();

        if ($this->filter === 'unread') {
            $q->whereNull('read_at');
        } elseif (str_starts_with($this->filter, 'type:')) {
            $q->where('type', substr($this->filter, 5));
        } elseif (str_starts_with($this->filter, 'level:')) {
            $q->where('level', substr($this->filter, 6));
        }

        return $q->take(200)->get();
    }

    /** Category + level breakdown for the left sidebar. */
    #[Computed]
    public function stats(): array
    {
        $all = Alert::visibleTo($this->site, Auth::user())->get(['type', 'level', 'read_at']);

        return [
            'total'   => $all->count(),
            'unread'  => $all->whereNull('read_at')->count(),
            'byType'  => $all->groupBy('type')->map->count()->sortDesc(),
            'byLevel' => $all->groupBy('level')->map->count(),
        ];
    }

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->expanded = null;
    }

    /** Delete one alert — confirmation happens in the shared modal (data-confirm). */
    public function deleteAlert(int $id): void
    {
        Alert::visibleTo($this->site, Auth::user())->whereKey($id)->delete();
        if ($this->expanded === $id) {
            $this->expanded = null;
        }
        unset($this->alerts, $this->stats);
    }

    /** Delete every READ alert — confirmation happens in the shared modal. */
    public function clearRead(): void
    {
        Alert::visibleTo($this->site, Auth::user())->whereNotNull('read_at')->delete();
        $this->expanded = null;
        unset($this->alerts, $this->stats);
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
        if ($this->expanded) {
            Alert::visibleTo($this->site, Auth::user())->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
            unset($this->alerts, $this->stats);
        }
    }

    public function markAllRead(): void
    {
        Alert::visibleTo($this->site, Auth::user())->whereNull('read_at')->update(['read_at' => now()]);
        unset($this->alerts, $this->stats);
        $this->dispatch('toast', level: 'success', title: 'Alerts', message: 'All alerts marked as read.');
    }

    public function render()
    {
        return view('livewire.alerts-page');
    }
}
