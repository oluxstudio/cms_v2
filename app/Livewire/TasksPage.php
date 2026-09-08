<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Site;
use App\Models\Todo;
use App\Models\TodoItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tasks — the site's work board. Anyone on the team can create a task,
 * assign it to a teammate, break it into subtasks, move it through
 * To do → In progress → Done and talk about it in the task's comments.
 * Assignments and comments notify the people involved through the site's
 * Messages (right rail), so the whole team sees progress without leaving
 * the CMS. Invitations and roles live on the Team page.
 */
class TasksPage extends Component
{
    public string $siteId;

    /** all | open | in_progress | done | overdue | mine */
    #[Url(as: 'show')]
    public string $filter = 'all';

    // ── composer ──
    public bool $composing = false;

    public string $title = '';

    public string $description = '';

    public string $assignee = '';

    public string $priority = 'normal';

    public string $startAt = '';

    public string $dueAt = '';

    public string $items = '';

    // ── detail drawer ──
    public ?string $openId = null;

    public string $comment = '';

    public array $newItem = [];

    /** Item being edited in the drawer (id) + its form. */
    public ?string $editingItem = null;

    public array $itemForm = ['label' => '', 'description' => '', 'assignee' => '', 'startsAt' => '', 'endsAt' => ''];

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    #[Computed]
    public function tasks()
    {
        $q = Todo::visibleTo($this->site, Auth::user())
            ->with(['items', 'assignee:id,name,avatar', 'creator:id,name,avatar', 'comments.author:id,name,avatar'])
            ->withCount('comments');

        match ($this->filter) {
            'open', 'in_progress', 'done' => $q->where('status', $this->filter),
            'overdue' => $q->where('status', '!=', 'done')->whereNotNull('due_at')->where('due_at', '<', now()),
            'mine' => $q->where('assigned_user_id', Auth::id()),
            default => null,
        };

        return $q->orderByRaw("status = 'done'")->orderByRaw('due_at is null')->orderBy('due_at')->orderByDesc('created_at')->take(200)->get();
    }

    #[Computed]
    public function openTask(): ?Todo
    {
        return $this->openId
            ? Todo::visibleTo($this->site, Auth::user())->with(['items.assignee:id,name,avatar', 'comments.author:id,name,avatar', 'assignee:id,name,avatar', 'creator:id,name,avatar'])->find($this->openId)
            : null;
    }

    #[Computed]
    public function members()
    {
        return $this->site->members()->orderBy('name')->get(['users.id', 'name', 'avatar'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar, 'role' => $this->site->roleFor($u)]);
    }

    /** Tab counts: all / open / in progress / done / overdue / mine. */
    #[Computed]
    public function counts(): array
    {
        $all = Todo::visibleTo($this->site, Auth::user())->get(['id', 'status', 'due_at', 'assigned_user_id']);

        return [
            'all' => $all->count(),
            'open' => $all->where('status', 'open')->count(),
            'in_progress' => $all->where('status', 'in_progress')->count(),
            'done' => $all->where('status', 'done')->count(),
            'overdue' => $all->filter(fn ($t) => $t->isOverdue())->count(),
            'mine' => $all->where('assigned_user_id', Auth::id())->count(),
        ];
    }

    public function canManage(): bool
    {
        return $this->site->canManageTeam(Auth::user());
    }

    // ── create ────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignee' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,normal,high'],
            'startAt' => ['nullable', 'date'],
            'dueAt' => ['nullable', 'date', 'after_or_equal:startAt'],
        ], ['dueAt.after_or_equal' => 'The finish date must be on or after the start date.']);
        $assignee = $this->memberOrNull($this->assignee);

        $task = $this->site->todos()->create([
            'user_id' => Auth::id(),
            'assigned_user_id' => $assignee?->id,
            'title' => trim($this->title),
            'description' => trim($this->description) ?: null,
            'priority' => $this->priority,
            'status' => 'open',
            'due_at' => $this->dueAt ? Carbon::parse($this->dueAt)->endOfDay() : null,
        ]);
        foreach (preg_split('/\r\n|\r|\n/', $this->items) as $i => $line) {
            if (($line = trim($line)) !== '') {
                $task->items()->create(['label' => $line, 'sort' => $i + 1]);
            }
        }
        if ($assignee) {
            $this->notify($assignee, "You've been assigned a task: {$task->title}");
        }

        $this->reset('title', 'description', 'assignee', 'startAt', 'dueAt', 'items', 'composing');
        $this->priority = 'normal';
        $this->refresh();
        $this->dispatch('toast', level: 'success', title: 'Task created', message: $task->title);
    }

    // ── status / assignment ───────────────────────────────────────────

    public function setStatus(string $taskId, string $status): void
    {
        abort_unless(array_key_exists($status, Todo::STATUSES), 422);
        $task = $this->find($taskId);
        abort_unless($task->editableBy(Auth::user()), 403);
        $task->update(['status' => $status, 'completed_at' => $status === 'done' ? now() : null]);
        if ($status === 'done' && $task->creator && $task->creator->id !== Auth::id()) {
            $this->notify($task->creator, Auth::user()->name." completed: {$task->title}");
        }
        $this->refresh();
    }

    public function assign(string $taskId, string $userId): void
    {
        $task = $this->find($taskId);
        abort_unless($task->editableBy(Auth::user()) || $this->canManage(), 403);
        $user = $this->memberOrNull($userId);
        $task->update(['assigned_user_id' => $user?->id]);
        if ($user && $user->id !== Auth::id()) {
            $this->notify($user, Auth::user()->name." assigned you: {$task->title}");
        }
        $this->refresh();
    }

    public function deleteTask(string $taskId): void
    {
        $task = $this->find($taskId);
        abort_unless($task->editableBy(Auth::user()), 403);
        $task->delete();
        if ($this->openId === $taskId) {
            $this->openId = null;
        }
        $this->refresh();
    }

    // ── subtasks ──────────────────────────────────────────────────────

    public function toggleItem(string $itemId): void
    {
        $item = TodoItem::whereHas('todo', fn ($q) => $q->where('site_id', $this->siteId))->findOrFail($itemId);
        $item->update(['done' => ! $item->done]);
        $this->syncStatus($item->todo_id);
        $this->refresh();
    }

    public function addItem(string $taskId): void
    {
        $task = $this->find($taskId);
        $label = trim($this->newItem[$taskId] ?? '');
        if ($label === '') {
            return;
        }
        $task->items()->create(['label' => $label, 'sort' => (int) $task->items()->max('sort') + 1]);
        $this->newItem[$taskId] = '';
        $this->syncStatus($taskId);
        $this->refresh();
    }

    // ── detail + comments ─────────────────────────────────────────────

    public function open(string $taskId): void
    {
        $this->find($taskId);
        $this->openId = $taskId;
        $this->comment = '';
    }

    public function close(): void
    {
        $this->openId = null;
        $this->editingItem = null;
    }

    // ── task items: who / what / when ─────────────────────────────────

    public function editItem(string $itemId): void
    {
        $item = $this->findItem($itemId);
        $this->editingItem = $item->id;
        $this->itemForm = [
            'label' => $item->label,
            'description' => (string) $item->description,
            'assignee' => (string) $item->assigned_user_id,
            'startsAt' => $item->starts_at?->toDateString() ?? '',
            'endsAt' => $item->ends_at?->toDateString() ?? '',
        ];
        $this->resetErrorBag();
    }

    public function cancelItem(): void
    {
        $this->editingItem = null;
    }

    public function saveItem(): void
    {
        $item = $this->findItem((string) $this->editingItem);
        abort_unless($item->todo->editableBy(Auth::user()) || $this->canManage(), 403);
        $this->validate([
            'itemForm.label' => ['required', 'string', 'max:255'],
            'itemForm.description' => ['nullable', 'string', 'max:2000'],
            'itemForm.assignee' => ['nullable', 'string'],
            'itemForm.startsAt' => ['nullable', 'date'],
            'itemForm.endsAt' => ['nullable', 'date', 'after_or_equal:itemForm.startsAt'],
        ], ['itemForm.endsAt.after_or_equal' => 'The finish must be on or after the start.']);

        $assignee = $this->memberOrNull((string) $this->itemForm['assignee']);
        $wasAssignedTo = $item->assigned_user_id;
        $item->update([
            'label' => trim($this->itemForm['label']),
            'description' => trim($this->itemForm['description']) ?: null,
            'assigned_user_id' => $assignee?->id,
            'starts_at' => $this->itemForm['startsAt'] ? Carbon::parse($this->itemForm['startsAt'])->startOfDay() : null,
            'ends_at' => $this->itemForm['endsAt'] ? Carbon::parse($this->itemForm['endsAt'])->endOfDay() : null,
        ]);
        if ($assignee && $assignee->id !== $wasAssignedTo && $assignee->id !== Auth::id()) {
            $this->notify($assignee, Auth::user()->name." assigned you \"{$item->label}\" on task: {$item->todo->title}");
        }
        $this->editingItem = null;
        $this->refresh();
    }

    public function deleteItem(string $itemId): void
    {
        $item = $this->findItem($itemId);
        abort_unless($item->todo->editableBy(Auth::user()) || $this->canManage(), 403);
        $todoId = $item->todo_id;
        $item->delete();
        $this->syncStatus($todoId);
        $this->refresh();
    }

    private function findItem(string $itemId): TodoItem
    {
        return TodoItem::with('todo')->whereHas('todo', fn ($q) => $q->where('site_id', $this->siteId))->findOrFail($itemId);
    }

    public function addComment(): void
    {
        $task = $this->find((string) $this->openId);
        $this->validate(['comment' => ['required', 'string', 'max:2000']]);

        $task->comments()->create(['user_id' => Auth::id(), 'body' => trim($this->comment)]);
        $this->comment = '';

        // Everyone on the thread except the author hears about it.
        $people = collect([$task->creator, $task->assignee])
            ->merge($task->comments()->with('author')->get()->pluck('author'))
            ->filter()->unique('id')->reject(fn ($u) => $u->id === Auth::id());
        foreach ($people as $u) {
            $this->notify($u, Auth::user()->name." commented on \"{$task->title}\"");
        }
        unset($this->openTask, $this->tasks);
    }

    // ── helpers ───────────────────────────────────────────────────────

    private function find(string $taskId): Todo
    {
        return Todo::visibleTo($this->site, Auth::user())->findOrFail($taskId);
    }

    private function memberOrNull(string $userId): ?User
    {
        return $userId !== '' ? $this->site->members()->where('users.id', $userId)->first() : null;
    }

    /** A direct message in the site's Messages hub — shows as unread in the recipient's rail. */
    private function notify(User $to, string $body): void
    {
        Message::create([
            'site_id' => $this->site->id,
            'sender_id' => Auth::id(),
            'recipient_id' => $to->id,
            'body' => $body,
        ]);
    }

    /** Subtasks drive the status: all ticked → done, some → in progress, none → to do. */
    private function syncStatus(string $taskId): void
    {
        $task = Todo::with('items')->find($taskId);
        if (! $task || $task->items->isEmpty()) {
            return;
        }
        $done = $task->items->where('done', true)->count();
        $status = $done === $task->items->count() ? 'done' : ($done > 0 ? 'in_progress' : 'open');
        $task->update(['status' => $status, 'completed_at' => $status === 'done' ? ($task->completed_at ?? now()) : null]);
    }

    private function refresh(): void
    {
        unset($this->tasks, $this->counts, $this->openTask);
    }

    public function render()
    {
        return view('livewire.tasks-page');
    }
}
