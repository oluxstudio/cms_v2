<?php

use App\Livewire\TasksPage;
use App\Models\Message;
use App\Models\Site;
use App\Models\Todo;
use App\Models\User;
use Livewire\Livewire;

function taskSite(): array
{
    $owner = User::factory()->create();
    $mate = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'tasks-'.uniqid(), 'domain' => 'tasks-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner'], $mate->id => ['role' => 'editor']]);

    return [$owner, $mate, $site];
}

test('the todos url now lives at /tasks', function () {
    [$owner, , $site] = taskSite();
    $this->actingAs($owner)->get("/{$site->name}/todos")->assertRedirect("/{$site->name}/tasks");
    $this->actingAs($owner)->get("/{$site->name}/tasks")->assertOk()->assertSee('Create task');
});

test('a task can be created, assigned with a due date and subtasks, and the assignee is messaged', function () {
    [$owner, $mate, $site] = taskSite();

    Livewire::actingAs($owner)->test(TasksPage::class, ['siteId' => $site->id])
        ->set('composing', true)
        ->set('title', 'Photograph the salon')->set('assignee', $mate->id)->set('priority', 'high')
        ->set('dueAt', now()->addDays(2)->toDateString())->set('items', "Front\nInterior")
        ->call('create')->assertHasNoErrors()
        ->assertSee('Photograph the salon')->assertSee($mate->name)->assertSee('left');

    $task = Todo::where('site_id', $site->id)->first();
    expect($task->assigned_user_id)->toBe($mate->id)
        ->and($task->items()->count())->toBe(2)
        ->and($task->due_at)->not->toBeNull();
    expect(Message::where('recipient_id', $mate->id)->where('sender_id', $owner->id)->where('body', 'like', '%assigned a task%')->exists())->toBeTrue();
});

test('subtasks move the task through in progress to done, and tabs count each state', function () {
    [$owner, , $site] = taskSite();
    $task = $site->todos()->create(['user_id' => $owner->id, 'title' => 'Two steps', 'status' => 'open', 'priority' => 'normal']);
    [$a, $b] = [$task->items()->create(['label' => 'a', 'sort' => 1]), $task->items()->create(['label' => 'b', 'sort' => 2])];
    $site->todos()->create(['user_id' => $owner->id, 'title' => 'Late', 'status' => 'open', 'priority' => 'normal', 'due_at' => now()->subDay()]);

    $c = Livewire::actingAs($owner)->test(TasksPage::class, ['siteId' => $site->id]);
    expect($c->instance()->counts())->toMatchArray(['all' => 2, 'open' => 2, 'overdue' => 1]);

    $c->call('toggleItem', $a->id);
    expect($task->fresh()->status)->toBe('in_progress');
    $c->call('toggleItem', $b->id);
    expect($task->fresh()->status)->toBe('done')->and($task->fresh()->completed_at)->not->toBeNull();
    expect($c->instance()->counts())->toMatchArray(['done' => 1, 'in_progress' => 0]);

    $c->set('filter', 'overdue')->assertSee('Late')->assertDontSee('Two steps');
});

test('teammates comment on a task and everyone else on the thread is notified', function () {
    [$owner, $mate, $site] = taskSite();
    $task = $site->todos()->create(['user_id' => $owner->id, 'assigned_user_id' => $mate->id, 'title' => 'Discuss', 'status' => 'open', 'priority' => 'normal']);

    Livewire::actingAs($mate)->test(TasksPage::class, ['siteId' => $site->id])
        ->call('open', $task->id)->assertSee('No comments yet')
        ->set('comment', 'Started on this')->call('addComment')->assertHasNoErrors()
        ->assertSee('Started on this');

    expect($task->comments()->count())->toBe(1)
        ->and(Message::where('recipient_id', $owner->id)->where('body', 'like', '%commented on "Discuss"%')->exists())->toBeTrue()
        ->and(Message::where('recipient_id', $mate->id)->exists())->toBeFalse();

    // Changing assignee from the drawer notifies the new person.
    Livewire::actingAs($owner)->test(TasksPage::class, ['siteId' => $site->id])->call('assign', $task->id, $mate->id);
    expect(Message::where('recipient_id', $mate->id)->where('body', 'like', '%assigned you%')->exists())->toBeTrue();
});

test('outsiders cannot see or touch a site\'s tasks', function () {
    [, , $site] = taskSite();
    $task = $site->todos()->create(['title' => 'Private', 'status' => 'open', 'priority' => 'normal']);
    $this->actingAs(User::factory()->create())->get("/{$site->name}/tasks")->assertForbidden();
});

test('task items carry who / what / when, and dated ones build the timeline', function () {
    [$owner, $mate, $site] = taskSite();
    $task = $site->todos()->create(['user_id' => $owner->id, 'title' => 'Launch', 'status' => 'open', 'priority' => 'normal',
        'starts_at' => now()->startOfDay(), 'due_at' => now()->addDays(9)->endOfDay()]);
    $a = $task->items()->create(['label' => 'Design', 'sort' => 1]);
    $b = $task->items()->create(['label' => 'Undated', 'sort' => 2]);

    $c = Livewire::actingAs($owner)->test(TasksPage::class, ['siteId' => $site->id])
        ->call('open', $task->id)->assertSee('Task breakdown')->assertSee('Timeline')->assertSee('Whole task')
        ->call('editItem', $a->id)
        ->set('itemForm.description', 'Homepage mockups')->set('itemForm.assignee', $mate->id)
        ->set('itemForm.startsAt', now()->addDay()->toDateString())->set('itemForm.endsAt', now()->toDateString())
        ->call('saveItem')->assertHasErrors('itemForm.endsAt')
        ->set('itemForm.endsAt', now()->addDays(4)->toDateString())
        ->call('saveItem')->assertHasNoErrors()
        ->assertSee('Homepage mockups')->assertSee('↳ Design');

    $a->refresh();
    expect($a->assigned_user_id)->toBe($mate->id)->and($a->isScheduled())->toBeTrue()->and($b->fresh()->isScheduled())->toBeFalse();
    expect(Message::where('recipient_id', $mate->id)->where('body', 'like', '%assigned you "Design"%')->exists())->toBeTrue();

    $tl = $task->fresh()->load('items')->timeline();
    expect($tl['days'])->toBe(10)->and($tl['task'])->not->toBeNull()->and($tl['rows'])->toHaveCount(1)
        ->and($tl['rows'][0]['label'])->toBe('Design')->and($tl['rows'][0]['left'])->toBeGreaterThan(0);

    // A task with no dates anywhere has no timeline.
    $bare = $site->todos()->create(['user_id' => $owner->id, 'title' => 'Bare', 'status' => 'open', 'priority' => 'normal']);
    expect($bare->load('items')->timeline())->toBeNull();
});
