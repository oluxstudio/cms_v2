<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Message;
use App\Models\Site;
use App\Models\Todo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Renders an Alert / Message / Todo opened from the right rail, inside #MainContent.
 * Loads on the `rail-open` event; the layout switches its pane to 'detail' on the
 * same event (browser-side).
 */
class SiteRailDetail extends Component
{
    public string $siteId;

    public ?string $type = null;

    public ?string $itemId = null;

    #[On('rail-open')]
    public function open(string $type, string $id): void
    {
        $this->type = $type;
        $this->itemId = $id;
    }

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    #[Computed]
    public function item()
    {
        if (! $this->type || ! $this->itemId) {
            return null;
        }
        $user = Auth::user();

        return match ($this->type) {
            'alert' => Alert::visibleTo($this->site, $user)->find($this->itemId),
            'message' => Message::visibleTo($this->site, $user)->with('sender:id,name', 'recipient:id,name')->find($this->itemId),
            'todo' => Todo::visibleTo($this->site, $user)->with('items', 'creator:id,name', 'assignee:id,name')->find($this->itemId),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.site-rail-detail');
    }
}
