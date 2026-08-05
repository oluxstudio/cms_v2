<?php

namespace App\Livewire;

use App\Models\Site;
use Livewire\Component;

class DonationsPage extends Component
{
    public Site $site;

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    public function getDonationsProperty()
    {
        return $this->site->donations()->latest()->get();
    }

    public function getStatsProperty(): array
    {
        $currency = $this->site->currency ?? 'gbp';
        $raised   = (int) $this->site->donations()->where('status', 'paid')->sum('amount_cents');
        $count    = $this->site->donations()->where('status', 'paid')->count();

        return [
            'currency'    => $currency,
            'raisedMajor' => \App\Support\Money::format($raised, $currency),
            'count'       => $count,
            'avg'         => \App\Support\Money::format($count > 0 ? (int) round($raised / $count) : 0, $currency),
        ];
    }

    /** Delete a donation record (e.g. clearing test donations) —
     *  confirmation happens in the shared modal (data-confirm). */
    public function deleteDonation(int $id): void
    {
        $this->site->donations()->whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.donations-page');
    }
}
