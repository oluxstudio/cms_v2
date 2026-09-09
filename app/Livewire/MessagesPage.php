<?php

namespace App\Livewire;

use App\Models\AccountMember;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;
use App\Services\TaskLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Team inbox for ONE site/account: a pinned #Team channel (broadcasts) plus a
 * DM thread per member. Read state is per user; every bubble carries the
 * sender's role on THIS site, and an "Other inboxes" strip jumps to the same
 * page on the user's other accounts (each with its own unread badge).
 */
class MessagesPage extends Component
{
    public string $siteId;

    /** 'team' or a member's user id. */
    #[Url(as: 'with')]
    public string $thread = 'team';

    public string $body = '';

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    /** Everyone on this site's team: owner + legacy members + RBAC members. */
    #[Computed]
    public function team()
    {
        $ids = $this->site->members()->pluck('users.id')
            ->merge(AccountMember::where('account_id', $this->site->user_id)
                ->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $this->site->id))
                ->pluck('user_id'))
            ->push($this->site->user_id)
            ->filter()->unique();

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** user_id => role label on THIS site ("Owner", "Admin", custom role name…). */
    #[Computed]
    public function roleLabels(): array
    {
        $labels = [];
        if ($this->site->user) {
            $labels[$this->site->user->id] = 'Owner';
        }
        foreach ($this->site->members as $m) {
            $labels[$m->id] ??= ucfirst((string) ($m->pivot->role ?? 'member'));
        }
        // RBAC roles win over the legacy pivot when present.
        foreach (AccountMember::with('role:id,name')
            ->where('account_id', $this->site->user_id)
            ->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $this->site->id))
            ->get(['user_id', 'role_id']) as $am) {
            if ($am->role) {
                $labels[$am->user_id] = $am->role->name;
            }
        }

        return $labels;
    }

    /** Conversation list: #Team first, then each teammate with unread + last line. */
    #[Computed]
    public function conversations(): array
    {
        $me = Auth::user();
        $rows = [];

        $lastTeam = Message::where('site_id', $this->site->id)->whereNull('recipient_id')->latest()->first();
        $rows[] = [
            'key' => 'team',
            'name' => 'Team',
            'role' => 'everyone',
            'last' => $lastTeam?->body,
            'at' => $lastTeam?->created_at,
            'unread' => Message::where('site_id', $this->site->id)->whereNull('recipient_id')
                ->where('sender_id', '!=', $me->id)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))->count(),
        ];

        foreach ($this->team as $person) {
            if ($person->id === $me->id) {
                continue;
            }
            $last = $this->dmQuery($person->id)->latest()->first();
            $rows[] = [
                'key' => (string) $person->id,
                'name' => $person->name,
                'role' => $this->roleLabels[$person->id] ?? 'member',
                'last' => $last?->body,
                'at' => $last?->created_at,
                'unread' => Message::where('site_id', $this->site->id)
                    ->where('sender_id', $person->id)->where('recipient_id', $me->id)
                    ->whereNull('read_at')->count(),
            ];
        }

        return $rows;
    }

    private function dmQuery(string $otherId)
    {
        $me = Auth::id();

        return Message::where('site_id', $this->site->id)->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('sender_id', $me)->where('recipient_id', $otherId))
            ->orWhere(fn ($q) => $q->where('sender_id', $otherId)->where('recipient_id', $me)));
    }

    /** The open thread's messages, oldest first; opening marks them read for me. */
    #[Computed]
    public function threadMessages()
    {
        $query = $this->thread === 'team'
            ? Message::where('site_id', $this->site->id)->whereNull('recipient_id')
            : $this->dmQuery($this->thread);

        $messages = $query->with('sender:id,name')->oldest()->take(300)->get();

        $me = Auth::user();
        foreach ($messages as $m) {
            if (! $m->isReadBy($me)) {
                $m->markReadFor($me);
            }
        }

        return $messages;
    }

    /** The user's OTHER inboxes (owned + member sites) with unread badges. */
    #[Computed]
    public function otherInboxes(): array
    {
        $me = Auth::user();
        $names = $me->sites()->pluck('name')->merge($me->memberSiteNames())->unique()
            ->reject(fn ($n) => $n === $this->site->name)->values();

        return Site::whereIn('name', $names)->get(['id', 'name'])
            ->map(fn (Site $s) => [
                'name' => $s->name,
                'label' => (string) ($s->getAttr('business_name') ?: ucwords(str_replace('-', ' ', $s->name))),
                'unread' => Message::unreadCountFor($s, $me),
            ])->all();
    }

    #[Computed]
    public function canSend(): bool
    {
        return $this->site->allows(Auth::user(), 'messages.send');
    }

    public function openThread(string $key): void
    {
        $this->thread = $key;
        unset($this->threadMessages, $this->conversations);
        $this->dispatch('carousel-go', i: 1); // mobile: slide to the conversation
    }

    public function send(): void
    {
        abort_unless($this->canSend, 403);
        $this->validate(['body' => ['required', 'string', 'max:2000']]);

        $recipientId = $this->thread === 'team' ? null : $this->thread;
        if ($recipientId !== null && ! $this->team->contains('id', $recipientId)) {
            return;
        }

        $message = $this->site->messages()->create([
            'sender_id' => Auth::id(),
            'recipient_id' => $recipientId,
            'body' => trim($this->body),
        ]);

        // Notify the other side — hourly-deduped so bursts don't spam the bell.
        try {
            $sender = Auth::user();
            app(TaskLogger::class)->alert(
                $this->site,
                '💬 New message from '.$sender->name,
                'message', 'info',
                Str::limit($message->body, 120),
                $recipientId ? User::find($recipientId) : null,
                'all',
                url($this->site->name.'/messages'.($recipientId ? '?with='.$sender->id : '')),
                [],
                'msg:'.($recipientId ?: 'team').':'.$sender->id.':'.now()->format('Y-m-d-H'),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $this->reset('body');
        unset($this->threadMessages, $this->conversations);
        $this->dispatch('message-sent');
    }

    /** Delete one of MY messages — confirmation via the shared data-confirm modal. */
    public function deleteMessage(string $id): void
    {
        Message::where('site_id', $this->site->id)->where('sender_id', Auth::id())->whereKey($id)->delete();
        unset($this->threadMessages, $this->conversations);
    }

    public function render()
    {
        return view('livewire.messages-page');
    }
}
