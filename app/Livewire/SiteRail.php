<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Message;
use App\Models\Site;
use App\Models\Todo;
use App\Models\TodoItem;
use App\Services\TaskLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Right-rail hub: Alerts · Messages · Todos. All data is site-scoped and
 * filtered by RBAC (Alert/Message/Todo visibleTo scopes). Clicking an item
 * dispatches `rail-open`, which the layout catches to show it in #MainContent.
 */
class SiteRail extends Component
{
    public string $siteId;

    public string $tab = 'alerts';

    // composers
    public string $newTodo = '';

    public string $newMessage = '';

    public array $newItem = [];      // todoId => label being typed

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    #[Computed]
    public function alerts()
    {
        return Alert::visibleTo($this->site, Auth::user())->latest()->take(7)->get();
    }

    #[Computed]
    public function messages()
    {
        return Message::visibleTo($this->site, Auth::user())
            ->with('sender:id,name')->latest()->take(30)->get();
    }

    #[Computed]
    public function todos()
    {
        return Todo::visibleTo($this->site, Auth::user())
            ->with(['items', 'assignee:id,name'])
            ->orderByRaw("status = 'done'")->orderByDesc('id')->take(40)->get();
    }

    /** Header rail-buttons (layout) switch the active tab from outside. */
    #[On('rail-tab')]
    public function setTab(string $tab): void
    {
        if (in_array($tab, ['alerts', 'messages', 'todos'], true)) {
            $this->tab = $tab;
        }
    }

    public function counts(): array
    {
        $u = Auth::user();

        return [
            'alerts' => Alert::visibleTo($this->site, $u)->whereNull('read_at')->count(),
            'messages' => Message::visibleTo($this->site, $u)->whereNull('read_at')
                ->where('sender_id', '!=', $u->id)->count(),
            'todos' => Todo::visibleTo($this->site, $u)->where('status', 'open')->count(),
        ];
    }

    // ── Open in #MainContent ────────────────────────────────────────
    public function open(string $type, string $id): void
    {
        if ($type === 'alert') {
            Alert::visibleTo($this->site, Auth::user())->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
        } elseif ($type === 'message') {
            Message::visibleTo($this->site, Auth::user())->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
        }
        // Browser + Livewire event — layout switches the pane, detail component loads it.
        $this->dispatch('rail-open', type: $type, id: $id);
    }

    public function markAllAlertsRead(): void
    {
        Alert::visibleTo($this->site, Auth::user())->whereNull('read_at')->update(['read_at' => now()]);
        unset($this->alerts);
    }

    // ── Todos ───────────────────────────────────────────────────────
    public function addTodo(): void
    {
        $title = trim($this->newTodo);
        if ($title === '') {
            return;
        }
        $todo = $this->site->todos()->create([
            'user_id' => Auth::id(),
            'title' => $title,
            'status' => 'open',
        ]);
        $this->newTodo = '';
        unset($this->todos);
        $this->dispatch('toast', level: 'success', title: 'Todo added', message: $todo->title);
    }

    public function addItem(string $todoId): void
    {
        $todo = Todo::visibleTo($this->site, Auth::user())->findOrFail($todoId);
        $label = trim($this->newItem[$todoId] ?? '');
        if ($label === '') {
            return;
        }
        $todo->items()->create(['label' => $label, 'sort' => (int) $todo->items()->max('sort') + 1]);
        $this->newItem[$todoId] = '';
        unset($this->todos);
    }

    public function toggleItem(string $itemId): void
    {
        $item = TodoItem::whereHas('todo', fn ($q) => $q->where('site_id', $this->siteId))->findOrFail($itemId);
        $item->update(['done' => ! $item->done]);
        $this->syncTodoStatus($item->todo_id);
        unset($this->todos);
    }

    public function toggleTodo(string $todoId): void
    {
        $todo = Todo::visibleTo($this->site, Auth::user())->findOrFail($todoId);
        abort_unless($todo->editableBy(Auth::user()), 403);
        $done = $todo->status !== 'done';
        $todo->update(['status' => $done ? 'done' : 'open', 'completed_at' => $done ? now() : null]);
        unset($this->todos);
        if ($done) {
            app(TaskLogger::class)->record($this->site, Auth::user(), 'Todo completed', 'success', 'todo.completed', $todo->title, [], alertTeam: true);
            $this->dispatch('toast', level: 'success', title: 'Todo completed', message: $todo->title);
        }
    }

    public function deleteTodo(string $todoId): void
    {
        $todo = Todo::visibleTo($this->site, Auth::user())->findOrFail($todoId);
        abort_unless($todo->editableBy(Auth::user()), 403);
        $todo->delete();
        unset($this->todos);
    }

    private function syncTodoStatus(string $todoId): void
    {
        $todo = Todo::with('items')->find($todoId);
        if (! $todo || $todo->items->isEmpty()) {
            return;
        }
        $allDone = $todo->items->every(fn ($i) => $i->done);
        $todo->update([
            'status' => $allDone ? 'done' : 'open',
            'completed_at' => $allDone ? ($todo->completed_at ?? now()) : null,
        ]);
    }

    // ── Messages ────────────────────────────────────────────────────
    public function sendMessage(): void
    {
        $body = trim($this->newMessage);
        if ($body === '') {
            return;
        }
        $this->site->messages()->create([
            'sender_id' => Auth::id(),
            'recipient_id' => null, // broadcast to the team
            'body' => $body,
        ]);
        $this->newMessage = '';
        unset($this->messages);
        $this->dispatch('toast', level: 'info', title: 'Message sent', message: 'Posted to the team.');
    }

    public function render()
    {
        return view('livewire.site-rail', ['counts' => $this->counts()]);
    }
}
