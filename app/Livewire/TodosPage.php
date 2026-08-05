<?php

namespace App\Livewire;

use App\Models\Site;
use App\Models\Todo;
use App\Models\TodoItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TodosPage extends Component
{
    public int $siteId;

    #[Url(as: 'show')]
    public string $filter = 'all';   // all | open | done

    // composer
    public bool $composing = false;
    public string $title = '';
    public string $assignee = '';
    public string $priority = 'normal';
    public string $items = '';       // newline-separated subtasks

    public array $newItem = [];      // todoId => label

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    #[Computed]
    public function todos()
    {
        $q = Todo::visibleTo($this->site, Auth::user())->with(['items', 'assignee:id,name', 'creator:id,name']);
        if (in_array($this->filter, ['open', 'done'], true)) {
            $q->where('status', $this->filter);
        }

        return $q->orderByRaw("status = 'done'")->orderByDesc('id')->take(200)->get();
    }

    #[Computed]
    public function members()
    {
        return $this->site->members()->orderBy('name')->get(['users.id', 'name']);
    }

    #[Computed]
    public function stats(): array
    {
        $all   = Todo::visibleTo($this->site, Auth::user())->withCount(['items', 'items as done_items_count' => fn ($q) => $q->where('done', true)])->get();
        $open  = $all->where('status', 'open')->count();
        $done  = $all->where('status', 'done')->count();
        $items = $all->sum('items_count');
        $doneItems = $all->sum('done_items_count');

        return [
            'total'      => $all->count(),
            'open'       => $open,
            'done'       => $done,
            'completion' => $all->count() ? (int) round($done / $all->count() * 100) : 0,
            'byPriority' => $all->groupBy('priority')->map->count(),
            'items'      => $items,
            'doneItems'  => $doneItems,
        ];
    }

    public function create(): void
    {
        $this->validate(['title' => ['required', 'string', 'max:255']]);

        $todo = $this->site->todos()->create([
            'user_id'          => Auth::id(),
            'assigned_user_id' => $this->assignee !== '' ? (int) $this->assignee : null,
            'title'            => trim($this->title),
            'priority'         => $this->priority,
            'status'           => 'open',
        ]);

        foreach (preg_split('/\r\n|\r|\n/', $this->items) as $i => $line) {
            $line = trim($line);
            if ($line !== '') {
                $todo->items()->create(['label' => $line, 'sort' => $i + 1]);
            }
        }

        $this->reset('title', 'assignee', 'items', 'composing');
        $this->priority = 'normal';
        unset($this->todos, $this->stats);
        $this->dispatch('toast', level: 'success', title: 'Todo created', message: $todo->title);
    }

    public function toggleItem(int $itemId): void
    {
        $item = TodoItem::whereHas('todo', fn ($q) => $q->where('site_id', $this->siteId))->findOrFail($itemId);
        $item->update(['done' => ! $item->done]);
        $this->syncStatus($item->todo_id);
        unset($this->todos, $this->stats);
    }

    public function addItem(int $todoId): void
    {
        $todo  = Todo::visibleTo($this->site, Auth::user())->findOrFail($todoId);
        $label = trim($this->newItem[$todoId] ?? '');
        if ($label === '') {
            return;
        }
        $todo->items()->create(['label' => $label, 'sort' => (int) $todo->items()->max('sort') + 1]);
        $this->newItem[$todoId] = '';
        unset($this->todos, $this->stats);
    }

    public function toggleTodo(int $todoId): void
    {
        $todo = Todo::visibleTo($this->site, Auth::user())->findOrFail($todoId);
        abort_unless($todo->editableBy(Auth::user()), 403);
        $done = $todo->status !== 'done';
        $todo->update(['status' => $done ? 'done' : 'open', 'completed_at' => $done ? now() : null]);
        unset($this->todos, $this->stats);
    }

    public function deleteTodo(int $todoId): void
    {
        $todo = Todo::visibleTo($this->site, Auth::user())->findOrFail($todoId);
        abort_unless($todo->editableBy(Auth::user()), 403);
        $todo->delete();
        unset($this->todos, $this->stats);
    }

    private function syncStatus(int $todoId): void
    {
        $todo = Todo::with('items')->find($todoId);
        if (! $todo || $todo->items->isEmpty()) {
            return;
        }
        $allDone = $todo->items->every(fn ($i) => $i->done);
        $todo->update(['status' => $allDone ? 'done' : 'open', 'completed_at' => $allDone ? ($todo->completed_at ?? now()) : null]);
    }

    public function render()
    {
        return view('livewire.todos-page');
    }
}
