<?php

use App\Models\FormResponse;
use App\Models\Site;
use App\Models\User;
use App\Services\FormSourceExtractor;
use Illuminate\Support\Facades\File;

// A template form's named hidden inputs (which leader a message is for, a
// prepared subject …) are real form fields: stored with every submission,
// never shown to visitors.

it('extracts named hidden inputs as hidden fields and stores what is sent in them', function () {
    $root = storage_path('framework/testing/hidden-form-'.uniqid());
    File::ensureDirectoryExists("$root/app/components");
    File::put("$root/app/components/LeaderContactForm.vue", <<<'VUE'
<template>
  <form class="profile-form" data-olx-form="leader-contact" @submit.prevent="send">
    <input type="hidden" name="member" :value="member">
    <input type="hidden" name="subject" :value="subject">
    <input type="hidden" :value="noName">
    <input type="text" name="first" placeholder="First Name" required>
    <input type="email" name="email" placeholder="Email Address" required>
    <textarea name="message" placeholder="Your Message" required></textarea>
    <button class="btn" type="submit">Send Message</button>
  </form>
</template>
VUE);
    $forms = collect(app(FormSourceExtractor::class)->fromSources($root))->keyBy('name');
    File::deleteDirectory($root);

    $fields = collect($forms['leader-contact']['fields'])->keyBy('key');
    expect($fields->keys()->all())->toBe(['member', 'subject', 'first', 'email', 'message'])   // the unnamed hidden input is ignored
        ->and($fields['member']['hidden'])->toBeTrue()->and($fields['member']['required'])->toBeFalse()
        ->and($fields['subject']['hidden'])->toBeTrue()
        ->and($fields['email'])->not->toHaveKey('hidden');

    // A site with that form keeps the hidden values of each submission.
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'hf-'.uniqid().'.test']);
    $form = $site->forms()->create(['name' => 'leader-contact', 'title' => 'Leader Contact', 'fields' => $forms['leader-contact']['fields'], 'is_active' => true]);
    $this->postJson("/api/sites/{$site->name}/form/leader-contact", [
        'member' => 'Pastor Michael Adebayo', 'subject' => 'Message for Pastor Michael Adebayo',
        'first' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello!', '_hp' => '',
    ])->assertCreated();

    $saved = FormResponse::where('form_id', $form->id)->latest()->first()->fields;
    expect($saved['member'])->toBe('Pastor Michael Adebayo')
        ->and($saved['subject'])->toBe('Message for Pastor Michael Adebayo')
        ->and($saved['message'])->toBe('Hello!');
});

it('emails the site owner about a new submission, with each field and a button to the response', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $ownerEmail = 'owner-'.uniqid().'@example.com';
    $owner = User::factory()->create(['email' => $ownerEmail]);
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'hf-'.uniqid().'.test']);
    $form = $site->forms()->create(['name' => 'leader-contact', 'title' => 'Leader Contact', 'is_active' => true, 'fields' => [
        ['key' => 'member', 'label' => 'Member', 'type' => 'text', 'hidden' => true],
        ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'hidden' => true],
        ['key' => 'first', 'label' => 'First Name', 'type' => 'text', 'required' => true],
        ['key' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
        ['key' => 'message', 'label' => 'Your Message', 'type' => 'textarea'],
    ]]);

    $this->postJson("/api/sites/{$site->name}/form/leader-contact", [
        'member' => 'Pastor Michael Adebayo', 'subject' => 'Message for Pastor Michael Adebayo',
        'first' => 'Ada', 'email' => 'ada@example.com', 'message' => "Line one\nLine two", '_hp' => '',
    ])->assertCreated();
    $response = FormResponse::where('form_id', $form->id)->latest()->first();

    \Illuminate\Support\Facades\Mail::assertQueued(App\Mail\FormSubmissionNotification::class, function ($mail) use ($site, $response, $ownerEmail) {
        $html = $mail->render();

        return $mail->hasTo($ownerEmail)
            && str_contains($mail->envelope()->subject, 'New Leader Contact form submission on')
            && str_contains($mail->envelope()->subject, '— Message for Pastor Michael Adebayo')
            && $mail->rows() === ['Member' => 'Pastor Michael Adebayo', 'Subject' => 'Message for Pastor Michael Adebayo',
                'First Name' => 'Ada', 'Email Address' => 'ada@example.com', 'Your Message' => "Line one\nLine two"]
            && str_contains($html, 'Line one<br>')
            && str_contains($html, 'View this response')
            && str_contains($html, route('site.forms.response', [$site->name, $response->id]));
    });
});
