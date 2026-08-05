<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MessagesPage extends Component
{
    public int $siteId;

    public bool $composing = false;
    public string $recipient = 'team';   // 'team' or a user id
    public string $body = '';

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    #[Computed]
    public function messages()
    {
        return Message::visibleTo($this->site, Auth::user())
            ->with('sender:id,name', 'recipient:id,name')->latest()->take(200)->get();
    }

    #[Computed]
    public function members()
    {
        return $this->site->members()->orderBy('name')->get(['users.id', 'name']);
    }

    #[Computed]
    public function stats(): array
    {
        $me  = Auth::id();
        $all = Message::visibleTo($this->site, Auth::user())->get(['sender_id', 'recipient_id', 'read_at']);

        return [
            'total'      => $all->count(),
            'unread'     => $all->whereNull('read_at')->where('sender_id', '!=', $me)->count(),
            'broadcasts' => $all->whereNull('recipient_id')->count(),
            'direct'     => $all->whereNotNull('recipient_id')->count(),
            'sent'       => $all->where('sender_id', $me)->count(),
        ];
    }

    public function send(): void
    {
        $this->validate(['body' => ['required', 'string', 'max:2000']]);

        $recipientId = $this->recipient === 'team' ? null : (int) $this->recipient;
        if ($recipientId && ! $this->site->members()->whereKey($recipientId)->exists()) {
            $recipientId = null;
        }

        $this->site->messages()->create([
            'sender_id'    => Auth::id(),
            'recipient_id' => $recipientId,
            'body'         => trim($this->body),
        ]);

        $this->reset('body', 'composing');
        $this->recipient = 'team';
        unset($this->messages, $this->stats);
        $this->dispatch('toast', level: 'success', title: 'Message sent',
            message: $recipientId ? 'Delivered.' : 'Posted to the team.');
    }

    public function markRead(int $id): void
    {
        Message::visibleTo($this->site, Auth::user())->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
        unset($this->messages, $this->stats);
    }

    /** Delete a message — confirmation happens in the shared modal (data-confirm). */
    public function deleteMessage(int $id): void
    {
        Message::visibleTo($this->site, Auth::user())->whereKey($id)->delete();
        unset($this->messages, $this->stats);
    }

    public function render()
    {
        return view('livewire.messages-page');
    }
}
