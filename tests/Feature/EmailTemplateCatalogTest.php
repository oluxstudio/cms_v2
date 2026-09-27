<?php

use App\Models\Site;
use App\Models\User;
use App\Support\EmailTemplate;
use App\Support\EmailTemplateCatalog;

function etcSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'etc-'.uniqid(),
        'domain' => 'etc-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 't',
    ]);

    return [$owner, $site];
}

test('every catalog entry is complete and its dynamic sections have partials', function () {
    foreach (EmailTemplateCatalog::all() as $key => $entry) {
        expect($entry)->toHaveKeys(['label', 'description', 'group', 'feature', 'subject', 'sections', 'editable', 'dynamic', 'placeholders', 'sample'])
            ->and($entry['sections'])->not->toBeEmpty();
        foreach ($entry['sections'] as $s) {
            expect($s)->toHaveKeys(['key', 'enabled', 'text']);
        }
        // Every editable key exists in the section list; dynamic keys have blade partials.
        $sectionKeys = array_column($entry['sections'], 'key');
        foreach ($entry['editable'] as $e) {
            expect($sectionKeys)->toContain($e);
        }
        foreach (array_keys($entry['dynamic']) as $d) {
            expect(view()->exists('emails.sections.'.$d))->toBeTrue("missing partial for {$key}.{$d}");
        }
    }
});

test('forKey returns catalog defaults, honours customisation, and reset forgets it', function () {
    [, $site] = etcSite();

    $tpl = EmailTemplate::forKey($site, 'booking_confirmed');
    expect($tpl['subject'])->toBe('Your booking with {site} {status_verb} — {reference}')
        ->and(collect($tpl['sections'])->firstWhere('key', 'booking_summary'))->not->toBeNull();

    EmailTemplate::saveFor($site, 'booking_confirmed', 'Booked! {reference}', [
        ['key' => 'greeting', 'enabled' => false, 'text' => null],
        ['key' => 'intro', 'enabled' => true, 'text' => 'See you soon {name}.'],
    ]);
    $tpl = EmailTemplate::forKey($site->fresh(), 'booking_confirmed');
    expect($tpl['subject'])->toBe('Booked! {reference}')
        ->and(collect($tpl['sections'])->firstWhere('key', 'greeting')['enabled'])->toBeFalse()
        ->and(collect($tpl['sections'])->firstWhere('key', 'intro')['text'])->toBe('See you soon {name}.')
        // missing defaults are appended
        ->and(collect($tpl['sections'])->firstWhere('key', 'booking_summary'))->not->toBeNull();
    expect(EmailTemplateCatalog::isCustomized($site, 'booking_confirmed'))->toBeTrue();

    EmailTemplate::resetFor($site, 'booking_confirmed');
    expect(EmailTemplateCatalog::isCustomized($site->fresh(), 'booking_confirmed'))->toBeFalse()
        ->and(EmailTemplate::forKey($site->fresh(), 'booking_confirmed')['subject'])
        ->toBe('Your booking with {site} {status_verb} — {reference}');
});

test('the receipt template still maps to its legacy attribute names', function () {
    [, $site] = etcSite();
    EmailTemplate::saveFor($site, 'receipt', 'Got it {name}', EmailTemplate::defaultSections('receipt'));

    expect($site->getAttr('email.receipt_subject'))->toBe('Got it {name}')
        ->and($site->getAttr('email.receipt_sections'))->not->toBeNull()
        ->and(EmailTemplate::siteDefault($site->fresh())['subject'])->toBe('Got it {name}');
});

test('estimate_quote honours the legacy estimator attrs when no template is stored', function () {
    [, $site] = etcSite();
    $site->setAttr('estimator.email_subject', 'Old subject {reference}');
    $site->setAttr('estimator.email_body', 'Old body {name}');

    $tpl = EmailTemplate::forKey($site, 'estimate_quote');
    expect($tpl['subject'])->toBe('Old subject {reference}')
        ->and(collect($tpl['sections'])->firstWhere('key', 'intro')['text'])->toBe('Old body {name}');
});

test('fill substitutes every ctx token and keeps the receipt field helpers', function () {
    $out = EmailTemplate::fill('Hi {name}, ref {reference} at {site}: {field:email} / {fields}',
        ['name' => 'Jo', 'reference' => 'BK-1', 'site' => 'Graceway'],
        ['email' => 'jo@x.com', 'budget' => '£1k']);
    expect($out)->toContain('Hi Jo, ref BK-1 at Graceway')
        ->toContain('jo@x.com')
        ->toContain('Budget: £1k');
    // {name} falls back to "there"
    expect(EmailTemplate::fill('Hi {name}'))->toBe('Hi there');
});
