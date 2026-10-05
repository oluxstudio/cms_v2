<?php

use App\Livewire\MarketplaceStore;
use App\Livewire\MarketplaceTemplatePage;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateCreator;
use App\Models\TemplateEntitlement;
use App\Models\TemplatePurchase;
use App\Models\User;
use App\Services\TemplateCommerce;
use Stripe\Checkout\Session;
use Stripe\StripeObject;

function storeTemplate(array $attrs = []): Template
{
    $creator = TemplateCreator::firstOrCreate(['slug' => 'olux-studio'], ['name' => 'Olux Studio']);

    return Template::create($attrs + [
        'uuid' => (string) Str::uuid(), 'creator_id' => $creator->id,
        'name' => $n = 'Tpl '.uniqid(), 'slug' => Str::slug($n),
        'short_description' => 'A lovely design.', 'description' => 'A lovely design for tests.',
        'category' => 'Trades', 'tags' => ['modern'], 'status' => 'published',
        'price_cents' => 0, 'currency' => 'gbp', 'source' => 'builtin', 'published_at' => now(),
    ]);
}

test('the store lists with filters, sort, pagination and in_library flags', function () {
    $user = User::factory()->create();
    $free = storeTemplate(['category' => 'Trades', 'tags' => ['modern']]);
    $paid = storeTemplate(['category' => 'Hair & beauty', 'price_cents' => 2900, 'tags' => ['bold']]);
    TemplateEntitlement::create(['user_id' => $user->id, 'template_id' => $free->id, 'source' => 'free']);

    $this->actingAs($user);

    // in_library flag
    $res = $this->getJson('/api/templates?q='.urlencode($free->name))->assertOk();
    expect($res->json('data.0.in_library'))->toBeTrue();

    // category filter
    $res = $this->getJson('/api/templates?category[]=Hair%20%26%20beauty')->assertOk();
    expect(collect($res->json('data'))->pluck('id'))->toContain($paid->id)->not->toContain($free->id);

    // price filter
    $res = $this->getJson('/api/templates?price=paid&q=Tpl')->assertOk();
    expect(collect($res->json('data'))->pluck('id'))->toContain($paid->id)->not->toContain($free->id);

    // tag filter
    $res = $this->getJson('/api/templates?tag[]=bold&q=Tpl')->assertOk();
    expect(collect($res->json('data'))->pluck('id'))->toContain($paid->id)->not->toContain($free->id);

    // sort price_asc puts free first among these two
    $res = $this->getJson('/api/templates?sort=price_asc&q=Tpl')->assertOk();
    $ids = collect($res->json('data'))->pluck('price_cents');
    expect($ids->first())->toBeLessThanOrEqual($ids->last());

    // pagination meta
    expect($res->json('meta.per_page'))->toBe(12);
});

test('free add-to-library is idempotent and paid templates are rejected', function () {
    $user = User::factory()->create();
    $free = storeTemplate();
    $paid = storeTemplate(['price_cents' => 4900]);
    $this->actingAs($user);

    $this->postJson('/api/templates/'.$free->id.'/library')->assertOk();
    $this->postJson('/api/templates/'.$free->id.'/library')->assertOk(); // idempotent
    expect(TemplateEntitlement::where('user_id', $user->id)->where('template_id', $free->id)->count())->toBe(1);

    $this->postJson('/api/templates/'.$paid->id.'/library')->assertStatus(422);
    expect(TemplateEntitlement::where('user_id', $user->id)->where('template_id', $paid->id)->exists())->toBeFalse();
});

test('a twice-delivered checkout webhook grants the library item once', function () {
    $user = User::factory()->create();
    $paid = storeTemplate(['price_cents' => 4900]);

    $session = new Session('cs_test_'.uniqid());
    $session->payment_intent = 'pi_test_1';
    $session->metadata = StripeObject::constructFrom([
        'kind' => 'template', 'template_id' => $paid->id, 'user_id' => $user->id,
    ]);

    app(TemplateCommerce::class)->fulfilFromSession($session);
    app(TemplateCommerce::class)->fulfilFromSession($session); // duplicate delivery

    expect(TemplateEntitlement::where('user_id', $user->id)->where('template_id', $paid->id)->count())->toBe(1)
        ->and(TemplatePurchase::where('stripe_checkout_session_id', $session->id)->count())->toBe(1);
    $e = TemplateEntitlement::where('user_id', $user->id)->where('template_id', $paid->id)->first();
    expect($e->source)->toBe('purchase')->and($e->price_paid_cents)->toBe(4900);
});

test('apply is refused outside the library and across accounts', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id]);
    $paid = storeTemplate(['price_cents' => 4900]);

    // Not in library → refused.
    $this->actingAs($owner)
        ->postJson('/api/sites/'.$site->name.'/design/apply', ['template_id' => $paid->id])
        ->assertForbidden();

    // Another account's library item never unlocks someone else's apply.
    $stranger = User::factory()->create();
    TemplateEntitlement::create(['user_id' => $stranger->id, 'template_id' => $paid->id, 'source' => 'purchase']);
    $this->actingAs($owner)
        ->postJson('/api/sites/'.$site->name.'/design/apply', ['template_id' => $paid->id])
        ->assertForbidden();

    // And a stranger cannot touch this site's design at all.
    $this->actingAs($stranger)
        ->postJson('/api/sites/'.$site->name.'/design/apply', ['template_id' => $paid->id])
        ->assertForbidden();
});

test('library listing is tenancy-scoped', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $t = storeTemplate();
    TemplateEntitlement::create(['user_id' => $a->id, 'template_id' => $t->id, 'source' => 'free']);

    $this->actingAs($b)->getJson('/api/library')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($a)->getJson('/api/library')->assertOk()->assertJsonCount(1, 'data');
});

test('the templates store lives at the site-scoped marketplace URL only', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id]);
    storeTemplate();

    $this->actingAs($owner)->get('/'.$site->name.'/marketplace')
        ->assertOk()->assertSee('Templates');
    // The account-level URL must not exist.
    $this->actingAs($owner)->get('/marketplace')->assertNotFound();
});

test('apply saves a restore point, enables required features, and revert restores', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'template' => 'blank']);
    $tpl = storeTemplate(['required_features' => ['polls']]);
    TemplateEntitlement::create(['user_id' => $owner->id, 'template_id' => $tpl->id, 'source' => 'free']);

    expect($site->hasFeature('polls'))->toBeFalse();

    $res = $this->actingAs($owner)
        ->postJson('/api/sites/'.$site->name.'/design/apply', ['template_id' => $tpl->id])
        ->assertOk();
    expect($res->json('restore_point'))->toBeTrue()
        ->and($res->json('features_enabled'))->toContain('polls');

    $site = Site::find($site->id);
    expect($site->hasFeature('polls'))->toBeTrue();
    $applied = $site->installedTemplates()->whereNotNull('applied_at')->first();
    expect($applied)->not->toBeNull()
        ->and($applied->previous_state['template'] ?? null)->toBe('blank');

    // Revert brings the renderer binding back.
    $this->actingAs($owner)->postJson('/api/sites/'.$site->name.'/design/revert')->assertOk();
    expect($site->fresh()->template)->toBe('blank');
});

test('store filters, sort, search, tab and page sync to the URL query string', function () {
    $user = User::factory()->create();
    $storeSite = Site::factory()->create(['user_id' => $user->id]);
    storeTemplate();

    Livewire\Livewire::actingAs($user)
        ->withQueryParams(['q' => 'salon', 'price' => 'free', 'sort' => 'newest', 'tab' => 'library', 'category' => ['Trades']])
        ->test(MarketplaceStore::class, ['site' => $storeSite])
        ->assertSet('q', 'salon')
        ->assertSet('price', 'free')
        ->assertSet('sort', 'newest')
        ->assertSet('tab', 'library')
        ->assertSet('cats', ['Trades']);
});

test('the template page applies a library template straight to the site it was opened from', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'template' => 'blank']);
    $tpl = storeTemplate();
    TemplateEntitlement::create(['user_id' => $owner->id, 'template_id' => $tpl->id, 'source' => 'free']);

    Livewire\Livewire::actingAs($owner)->test(MarketplaceTemplatePage::class, ['site' => $site, 'slug' => $tpl->slug])
        ->assertSee('Use on '.Illuminate\Support\Str::headline($site->name))
        ->call('useOnThisSite')
        ->assertRedirect(url($site->name.'/connect'));

    expect($site->installedTemplates()->whereNotNull('applied_at')->where('template_id', $tpl->id)->exists())->toBeTrue();

    Livewire\Livewire::actingAs($owner)->test(MarketplaceTemplatePage::class, ['site' => $site, 'slug' => $tpl->slug])
        ->assertSee('Used on '.Illuminate\Support\Str::headline($site->name));

    // Not in the library → refused, nothing applied.
    $other = storeTemplate();
    Livewire\Livewire::actingAs($owner)->test(MarketplaceTemplatePage::class, ['site' => $site, 'slug' => $other->slug])
        ->call('useOnThisSite')->assertNoRedirect();
    expect($site->installedTemplates()->where('template_id', $other->id)->exists())->toBeFalse();
});
