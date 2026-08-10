<?php

use App\Livewire\SiteFormsPage;
use App\Mail\FormSubmissionNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use App\Models\User;
use App\Support\EmailTemplate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function deliverySite(): array
{
    $owner = User::factory()->create(['email' => 'own-'.uniqid().'@test.com']);
    $site = Site::create(['user_id' => $owner->id, 'name' => 'fd-'.uniqid(), 'domain' => 'fd.test', 'owner' => $owner->name, 'description' => 't']);

    return [$owner, $site];
}

function deliveryForm(Site $site, ?array $delivery = null): Form
{
    return Form::create([
        'site_id' => $site->id,
        'name' => 'enquiry',
        'title' => 'Enquiry',
        'fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ],
        'delivery' => $delivery,
        'is_active' => true,
    ]);
}

test('a form submission delivers to both visitor and admin by default', function () {
    Mail::fake();
    [$owner, $site] = deliverySite();
    deliveryForm($site);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    Mail::assertSent(SubmissionReceipt::class, fn ($m) => $m->hasTo('sam@example.com'));
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo($owner->email));
});

test('disabling the admin notification stops the owner email', function () {
    Mail::fake();
    [$owner, $site] = deliverySite();
    deliveryForm($site, ['channels' => ['email' => ['enabled' => true, 'notify_visitor' => true, 'notify_admin' => false]]]);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    Mail::assertSent(SubmissionReceipt::class);
    Mail::assertNotSent(FormSubmissionNotification::class);
});

test('an admin_address override receives the notification instead of the owner', function () {
    Mail::fake();
    [$owner, $site] = deliverySite();
    deliveryForm($site, ['channels' => ['email' => ['enabled' => true, 'notify_admin' => true, 'admin_address' => 'ops@team.test']]]);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo('ops@team.test') && ! $m->hasTo($owner->email));
});

test('disabling the whole email channel sends nothing', function () {
    Mail::fake();
    [$owner, $site] = deliverySite();
    deliveryForm($site, ['channels' => ['email' => ['enabled' => false]]]);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    Mail::assertNothingSent();
});

test('the admin notification links to the specific response deep link', function () {
    Mail::fake();
    [$owner, $site] = deliverySite();
    deliveryForm($site);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    $response = FormResponse::latest('id')->first();
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->adminUrl === route('site.forms.response', [$site->name, $response->id]));
});

test('the response deep link opens that response in the forms page and marks it read', function () {
    [$owner, $site] = deliverySite();
    $form = deliveryForm($site);
    $response = FormResponse::create(['form_id' => $form->id, 'fields' => ['name' => 'Sam', 'email' => 'sam@example.com'], 'ip_address' => '127.0.0.1']);
    expect($response->read_at)->toBeNull();

    Livewire::actingAs($owner)->test(SiteFormsPage::class, ['site' => $site, 'openResponse' => $response->id])
        ->assertSet('mode', 'responses')
        ->assertSet('activeFormId', $form->id)
        ->assertSet('openId', $response->id);

    expect($response->fresh()->read_at)->not->toBeNull();
});

test('the delivery builder persists channel config on save', function () {
    [$owner, $site] = deliverySite();
    $form = deliveryForm($site);

    Livewire::actingAs($owner)->test(SiteFormsPage::class, ['site' => $site])
        ->call('goEdit', $form->id)
        ->set('fbDelivery.channels.email.notify_admin', false)
        ->set('fbDelivery.channels.email.admin_address', 'ops@team.test')
        ->call('saveForm');

    $cfg = $form->fresh()->deliveryConfig();
    expect($cfg['channels']['email']['notify_admin'])->toBeFalse()
        ->and($cfg['channels']['email']['admin_address'])->toBe('ops@team.test');
});

test('unimplemented channels are skipped, not errored', function () {
    Mail::fake();
    [$owner, $site] = deliverySite();
    // SMS enabled but not implemented — must be ignored, email still flows.
    deliveryForm($site, ['channels' => [
        'email' => ['enabled' => true, 'notify_admin' => true, 'notify_visitor' => true],
        'sms' => ['enabled' => true],
    ]]);

    $this->postJson("/api/sites/{$site->name}/form/enquiry", ['name' => 'Sam', 'email' => 'sam@example.com'])->assertCreated();

    Mail::assertSent(SubmissionReceipt::class);
    Mail::assertSent(FormSubmissionNotification::class);
});

test('reordering/disabling sections changes the rendered receipt', function () {
    [$owner, $site] = deliverySite();
    // Disable the summary section.
    $sections = collect(EmailTemplate::defaultSections())->map(function ($s) {
        if ($s['key'] === 'summary') {
            $s['enabled'] = false;
        }

        return $s;
    })->all();
    $site->setAttr('email.receipt_sections', json_encode($sections));

    $mail = new SubmissionReceipt($site, 'message', 'Jo', ['name' => 'Jo', 'email' => 'jo@example.com']);
    $html = $mail->render();

    expect($html)->not->toContain('What you sent');   // summary suppressed
});

test('a form with its own template uses it instead of the site default', function () {
    [$owner, $site] = deliverySite();
    // Site default subject.
    $site->setAttr('email.receipt_subject', 'Site default subject');
    $form = deliveryForm($site, null);
    $form->update(['email_template' => [
        'customized' => true,
        'subject' => 'Custom subject for {site}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'intro', 'enabled' => true, 'text' => 'This form has its OWN wording, {name}.'],
        ],
    ]]);

    $mail = new SubmissionReceipt($site, 'Enquiry form', 'Jo', ['name' => 'Jo'], $form->fresh());
    expect($mail->envelope()->subject)->toContain('Custom subject');
    expect($mail->render())->toContain('This form has its OWN wording, Jo.')
        ->and($mail->render())->not->toContain('Site default subject');
});

test('a form without a custom template falls back to the site default', function () {
    [$owner, $site] = deliverySite();
    $site->setAttr('email.receipt_subject', 'Site default subject {site}');
    $form = deliveryForm($site, null); // no email_template

    $mail = new SubmissionReceipt($site, 'Enquiry form', 'Jo', ['name' => 'Jo'], $form);
    expect($mail->envelope()->subject)->toContain('Site default subject');
});

test('the form builder seeds the template from the site default and saves a customised one', function () {
    [$owner, $site] = deliverySite();
    $site->setAttr('email.receipt_subject', 'Seed subject {site}');
    $form = deliveryForm($site);

    $component = Livewire::actingAs($owner)->test(SiteFormsPage::class, ['site' => $site])
        ->call('goEdit', $form->id);

    // Seeded from the site default, not yet customised.
    expect($component->get('fbTemplate.customized'))->toBeFalse()
        ->and($component->get('fbTemplate.subject'))->toContain('Seed subject');

    $sections = $component->get('fbTemplate.sections');
    $introIndex = collect($sections)->search(fn ($s) => $s['key'] === 'intro');

    $component->set('fbTemplate.customized', true)
        ->set('fbTemplate.subject', 'Only for this form')
        ->set("fbTemplate.sections.{$introIndex}.text", 'Form-specific hello')
        ->call('saveForm');

    $tpl = $form->fresh()->email_template;
    expect($tpl['customized'])->toBeTrue()
        ->and($tpl['subject'])->toBe('Only for this form')
        ->and(collect($tpl['sections'])->firstWhere('key', 'intro')['text'])->toBe('Form-specific hello');
});

test('turning customisation off stores no per-form template (uses site default)', function () {
    [$owner, $site] = deliverySite();
    $form = deliveryForm($site, null);
    $form->update(['email_template' => ['customized' => true, 'subject' => 'x', 'sections' => []]]);

    Livewire::actingAs($owner)->test(SiteFormsPage::class, ['site' => $site])
        ->call('goEdit', $form->id)
        ->set('fbTemplate.customized', false)
        ->call('saveForm');

    expect($form->fresh()->email_template)->toBeNull();
});

test('the receipt falls back to the app logo when none is set', function () {
    [$owner, $site] = deliverySite();
    // No email.logo attribute → the branded view embeds the app logo (a cid).
    $mail = new SubmissionReceipt($site, 'message', 'Jo', ['name' => 'Jo']);
    $html = $mail->render();

    // Falls back to the app brand mark served from public/images.
    expect($html)->toContain('images/olux-logo.png');
});
