<?php

use App\Livewire\EstimatesPage;
use App\Mail\EstimateQuoteMail;
use App\Mail\FormSubmissionNotification;
use App\Models\AccountMember;
use App\Models\Alert;
use App\Models\Estimate;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteActivityLog;
use App\Models\User;
use App\Services\Estimator\Formula;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function estimatorSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'est-'.uniqid(),
        'domain' => 'est.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->enableFeature('estimator');

    return [$owner, $site];
}

test('the formula evaluator computes and rejects safely', function () {
    expect(Formula::evaluate('area * rate + 25', ['area' => 10, 'rate' => 4.5]))->toBe(70.0)
        ->and(Formula::evaluate('(a + b) * 2', ['a' => 3, 'b' => 2]))->toBe(10.0)
        ->and(Formula::evaluate('10 / missing', []))->toBe(0.0) // div-by-zero → 0, unknown var → 0
        ->and(Formula::evaluate('system("ls")', []))->toBeNull()
        ->and(Formula::validate('area * unknown_key', ['area']))->toContain('unknown_key')
        ->and(Formula::validate('area * rate', ['area', 'rate']))->toBeNull();
});

test('admin names an estimator first, then builds its fields, calcs and email inside it', function () {
    [$owner, $site] = estimatorSite();

    $page = Livewire::actingAs($owner)->test(EstimatesPage::class, ['site' => $site]);

    // 1 · Create the named estimator — the editor opens on it.
    $page->set('newEstimatorName', 'Cleaner')->call('createEstimator');
    $cleaner = $site->estimators()->first();
    expect($cleaner->name)->toBe('Cleaner')
        ->and($cleaner->slug)->toBe('cleaner')
        ->and($page->get('selectedId'))->toBe($cleaner->id);

    // 2 · Fields live INSIDE the estimator.
    $page->call('openField', 0)->set('fLabel', 'Area to clean')->set('fType', 'number')
        ->set('fRequired', true)->call('saveField');
    $page->call('openField', 0)->set('fLabel', 'Hourly rate')->set('fType', 'fixed')
        ->set('fValue', '4.5')->call('saveField');
    expect($cleaner->fields()->pluck('key')->all())->toBe(['area_to_clean', 'hourly_rate']);

    // 3 · Calculator-built formula (buttons append tokens into cFormula).
    $page->call('openCalc', 0)->set('cName', 'Estimated cost')
        ->set('cFormula', ' area_to_clean  *  hourly_rate  +  25 ')->set('cFormat', 'money')->call('saveCalc');
    expect($cleaner->calcs()->first()->formula)->toBe('area_to_clean * hourly_rate + 25');

    // Bad formulas are refused with a message, not saved.
    $page->call('openCalc', 0)->set('cName', 'Broken')->set('cFormula', 'area_to_clean * nope')->call('saveCalc');
    expect($cleaner->calcs()->count())->toBe(1)
        ->and($page->get('errorMessage'))->toContain('nope');

    // 4 · Per-estimator email template.
    $page->set('eEmailSubject', 'Your {service} quote {reference}')
        ->set('eEmailBody', 'Hi {name}, your {service} total is {cost}.')->call('saveEstimatorSettings');
    expect($cleaner->fresh()->email_subject)->toBe('Your {service} quote {reference}');

    // A SECOND estimator coexists with its own field namespace.
    $page->set('newEstimatorName', 'Mover')->call('createEstimator');
    $page->call('openField', 0)->set('fLabel', 'Area to clean')->set('fType', 'number')->call('saveField');
    expect($site->estimators()->count())->toBe(2)
        ->and($site->estimators()->where('slug', 'mover')->first()->fields()->pluck('key')->all())->toBe(['area_to_clean']);
});

test('a visitor request runs the named estimator, emails both parties and posts a dashboard notification', function () {
    Mail::fake();
    [$owner, $site] = estimatorSite();
    $cleaner = $site->estimators()->create(['name' => 'Cleaner', 'slug' => 'cleaner',
        'email_subject' => 'Your {service} quote {reference}', 'email_body' => 'Hi {name}, total {cost}.']);
    $cleaner->fields()->create(['site_id' => $site->id, 'key' => 'area', 'label' => 'Area', 'type' => 'number', 'required' => true]);
    $cleaner->fields()->create(['site_id' => $site->id, 'key' => 'rate', 'label' => 'Rate', 'type' => 'fixed', 'value' => 4.5]);
    $cleaner->calcs()->create(['site_id' => $site->id, 'name' => 'Estimated cost', 'formula' => 'area * rate + 25', 'format' => 'money']);

    $res = $this->postJson("/api/sites/{$site->name}/estimator/request", [
        'estimator' => 'cleaner', 'fields' => ['area' => 10],
        'name' => 'Vera Visitor', 'email' => 'vera@example.com',
    ]);
    $res->assertStatus(201);

    // Estimate stored against the estimator with the calculated result (10 × 4.5 + 25 = £70).
    $estimate = Estimate::where('site_id', $site->id)->first();
    expect($estimate->estimator_id)->toBe($cleaner->id)
        ->and($estimate->results[0]['raw'])->toEqual(70)
        ->and($estimate->cost_low_cents)->toBe(7000);

    // Both emails; the visitor email uses THIS estimator's template.
    Mail::assertSent(EstimateQuoteMail::class, fn ($m) => $m->hasTo('vera@example.com'));
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo($owner->email));
    $mail = new EstimateQuoteMail($site, $estimate->fresh('estimator'), $estimate->results);
    expect($mail->envelope()->subject)->toBe("Your Cleaner quote {$estimate->reference}");

    // Dashboard notification: Alert + activity-feed entry.
    expect(Alert::where('site_id', $site->id)->where('type', 'estimate')->exists())->toBeTrue()
        ->and(SiteActivityLog::where('site_id', $site->id)->where('entity_type', 'estimate')->exists())->toBeTrue();

    // Required fields enforced; the estimators list is public config.
    $this->postJson("/api/sites/{$site->name}/estimator/request", [
        'estimator' => 'cleaner', 'fields' => [], 'name' => 'No Area', 'email' => 'no@example.com',
    ])->assertStatus(422);
    $this->getJson("/api/sites/{$site->name}/estimator/config")
        ->assertOk()
        ->assertJsonPath('estimators.0.key', 'cleaner')
        ->assertJsonPath('estimators.0.fields.0.key', 'area')   // visitor field exposed
        ->assertJsonMissing(['key' => 'rate']);                 // fixed set data stays private
});

test('members without estimates.manage cannot edit estimators', function () {
    [$owner, $site] = estimatorSite();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(EstimatesPage::class, ['site' => $site])
        ->set('newEstimatorName', 'Nope')->call('createEstimator')->assertStatus(403);
});
