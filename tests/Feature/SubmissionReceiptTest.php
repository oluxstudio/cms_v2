<?php

use App\Livewire\SiteEmailsPage;
use App\Mail\FormSubmissionNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Form;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function receiptSite(): array
{
    $owner = User::factory()->create(['email' => 'owner-'.uniqid().'@test.com']);
    $site = Site::create(['user_id' => $owner->id, 'name' => 'rc-'.uniqid(), 'domain' => 'rc.test', 'owner' => $owner->name, 'description' => 't']);

    return [$owner, $site];
}

test('a contact submission emails BOTH the visitor (branded receipt) and the admin', function () {
    Mail::fake();
    [$owner, $site] = receiptSite();

    $this->postJson("/api/sites/{$site->name}/contact", [
        'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hello there',
    ])->assertCreated();

    Mail::assertSent(SubmissionReceipt::class, fn ($m) => $m->hasTo('jo@example.com'));      // visitor
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo($owner->email)); // admin
});

test('an interest submission now emails the visitor a receipt too', function () {
    Mail::fake();
    [$owner, $site] = receiptSite();

    $this->postJson("/api/sites/{$site->name}/interest", [
        'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Interested!',
    ])->assertCreated();

    Mail::assertSent(SubmissionReceipt::class, fn ($m) => $m->hasTo('ada@example.com'));
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo($owner->email));
});

test('a form submission emails the visitor the branded receipt', function () {
    Mail::fake();
    [$owner, $site] = receiptSite();
    Form::create(['site_id' => $site->id, 'name' => 'enquiry', 'fields' => [
        ['key' => 'name', 'type' => 'text', 'required' => true],
        ['key' => 'email', 'type' => 'email', 'required' => true],
    ], 'is_active' => true]);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    Mail::assertSent(SubmissionReceipt::class, fn ($m) => $m->hasTo('sam@example.com'));
});

test('the receipt uses the admin-edited subject and body', function () {
    [$owner, $site] = receiptSite();
    $site->setAttr('email.receipt_subject', 'Cheers {name}, {site} got it');
    $site->setAttr('email.receipt_body', 'Hello {name} — thanks for your {type}.');

    $mail = new SubmissionReceipt($site, 'message', 'Jo', []);
    $env = $mail->envelope();
    expect($env->subject)->toBe('Cheers Jo, '.ucwords(str_replace('-', ' ', $site->name)).' got it');

    $rendered = $mail->render();
    expect($rendered)->toContain('Hello Jo — thanks for your message.');
});

test('the emails editor saves subject, body and requires forms.manage', function () {
    [$owner, $site] = receiptSite();

    Livewire::actingAs($owner)->test(SiteEmailsPage::class, ['site' => $site])
        ->set('subject', 'We got it, {name}')
        ->set('body', 'Hi {name}, thanks!')
        ->call('save');

    expect($site->getAttr('email.receipt_subject'))->toBe('We got it, {name}')
        ->and($site->getAttr('email.receipt_body'))->toBe('Hi {name}, thanks!');

    // A member without forms.manage is blocked.
    $outsider = User::factory()->create();
    Livewire::actingAs($outsider)->test(SiteEmailsPage::class, ['site' => $site])->assertStatus(403);
});
