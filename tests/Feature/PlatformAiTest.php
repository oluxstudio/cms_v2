<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformAiPage;
use App\Livewire\PlatformPlansPage;
use App\Models\AiUsage;
use App\Models\MembershipPlan;
use App\Models\PlatformSetting;
use App\Models\Site;
use App\Models\User;
use App\Services\AiQuota;
use App\Services\SiteAgent;
use App\Services\TwoFactor;
use App\Support\ConfigOverlay;
use App\Support\PlanCatalog;
use Livewire\Livewire;

function aiSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

beforeEach(function () {
    $this->settingsBefore = (int) PlatformSetting::max('id');
    $this->plansBefore = (int) MembershipPlan::max('id');
});
afterEach(function () {
    PlatformSetting::where('id', '>', $this->settingsBefore)->delete();
    MembershipPlan::where('id', '>', $this->plansBefore)->delete();
    ConfigOverlay::refresh();
    PlanCatalog::refresh();
});

test('an account over its monthly AI allowance gets a polite stop instead of a model call', function () {
    config(['plans.tiers.trial.limits.ai_tokens_month' => 1000]);
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'ai-'.uniqid().'.test']);
    $quota = app(AiQuota::class);

    expect($quota->exceeded($site))->toBeFalse();
    AiUsage::create(['site_id' => $site->id, 'user_id' => $owner->id, 'driver' => 'anthropic', 'model' => 'claude-sonnet-5',
        'input_tokens' => 700, 'output_tokens' => 400, 'tool_calls' => 0, 'created_at' => now()]);
    // Last month's use doesn't count.
    AiUsage::create(['site_id' => $site->id, 'user_id' => $owner->id, 'driver' => 'anthropic', 'model' => 'claude-sonnet-5',
        'input_tokens' => 99999, 'output_tokens' => 0, 'tool_calls' => 0, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    expect($quota->usedThisMonth($owner))->toBe(1100)->and($quota->exceeded($site))->toBeTrue();

    $result = app(SiteAgent::class)->ask($site, $owner, 'Add a page');
    expect($result['ok'])->toBeFalse()->and($result['text'])->toContain('AI allowance');
});

test('no cap means unlimited', function () {
    config(['plans.tiers.trial.limits.ai_tokens_month' => null]);
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'ai-'.uniqid().'.test']);
    AiUsage::create(['site_id' => $site->id, 'user_id' => $owner->id, 'driver' => 'x', 'model' => 'm',
        'input_tokens' => 10_000_000, 'output_tokens' => 0, 'tool_calls' => 0, 'created_at' => now()]);

    expect(app(AiQuota::class)->exceeded($site))->toBeFalse();
});

test('the master switch turns the assistant off everywhere', function () {
    config(['services.llm.driver' => 'deepseek', 'services.deepseek.key' => 'sk-test']);
    expect(SiteAgent::configured())->toBeTrue();

    Livewire::actingAs(aiSuper())->test(PlatformAiPage::class)->call('toggleEnabled');
    expect(SiteAgent::configured())->toBeFalse()->and(SiteAgent::hasCredentials())->toBeTrue();

    Livewire::actingAs(aiSuper())->test(PlatformAiPage::class)->call('toggleEnabled');
    expect(SiteAgent::configured())->toBeTrue();
});

test('the page estimates cost from the price table, which is editable', function () {
    $owner = User::factory()->create(['name' => 'Ai Heavy User '.uniqid()]);
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'ai-'.uniqid().'.test']);
    AiUsage::create(['site_id' => $site->id, 'user_id' => $owner->id, 'driver' => 'anthropic', 'model' => 'ai-test-model',
        'input_tokens' => 1_000_000, 'output_tokens' => 1_000_000, 'tool_calls' => 0, 'created_at' => now()]);

    expect(PlatformAiPage::cost('ai-test-model', 1_000_000, 1_000_000))->toBe(0.0);

    Livewire::actingAs(aiSuper())->test(PlatformAiPage::class)
        ->set('prices', [['model' => 'ai-test-model', 'in' => '2', 'out' => '8']])
        ->call('savePrices')->assertHasNoErrors()
        ->assertSee($owner->name);

    expect(PlatformAiPage::cost('ai-test-model', 1_000_000, 1_000_000))->toBe(10.0);

    $this->actingAs(User::factory()->create())->get('/admin/ai')->assertForbidden();
    $this->actingAs(aiSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])->get('/admin/ai')->assertOk();
});

test('the plan editor sets the monthly AI allowance', function () {
    Livewire::actingAs(aiSuper())->test(PlatformPlansPage::class)
        ->call('edit', 'starter')->set('form.ai_tokens', 250000)->call('save')->assertHasNoErrors();

    PlanCatalog::refresh();
    expect(config('plans.tiers.starter.limits.ai_tokens_month'))->toBe(250000);
});
