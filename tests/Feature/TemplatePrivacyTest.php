<?php

use App\Livewire\MarketplaceStore;
use App\Livewire\MarketplaceTemplatePage;
use App\Livewire\PlatformTemplatesPage;
use App\Livewire\TemplateDetailPage;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateCreator;
use App\Models\TemplateEntitlement;
use App\Models\User;
use App\Services\DesignService;
use App\Services\TemplateCatalog;
use App\Services\TemplateCommerce;
use App\Support\CuratedTemplates;
use App\Support\TemplateAccess;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function privTemplate(array $attrs = []): Template
{
    $creator = TemplateCreator::firstOrCreate(['slug' => 'olux-studio'], ['name' => 'Olux Studio']);

    return Template::create($attrs + [
        'uuid' => (string) Str::uuid(), 'creator_id' => $creator->id,
        'name' => $n = 'Private '.uniqid(), 'slug' => Str::slug($n),
        'category' => 'Church', 'status' => 'published', 'visibility' => 'private',
        'price_cents' => 0, 'currency' => 'gbp', 'source' => 'builtin', 'published_at' => now(),
    ]);
}

function privAccount(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'pv-'.substr(uniqid(), -8), 'domain' => 'pv-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('a private template never appears in public listings, the API or the public gallery', function () {
    $t = privTemplate();
    [$owner] = privAccount();

    expect(app(TemplateCatalog::class)->browse(['search' => $t->name])->total())->toBe(0)
        ->and(app(TemplateCatalog::class)->find($t->slug))->toBeNull();

    $this->actingAs($owner)->getJson('/api/templates?q='.urlencode($t->name))->assertOk()
        ->assertJsonMissing(['id' => $t->id]);
    $this->actingAs($owner)->postJson("/api/templates/{$t->id}/library")->assertNotFound();
    $this->get('/designs/'.$t->slug)->assertNotFound();

    // Account uploads (u-… keys) are never public gallery cards.
    expect(collect(CuratedTemplates::all())->pluck('key')->filter(fn ($k) => str_starts_with($k, 'u-')))->toBeEmpty();
});

test('only assigned accounts can see, open and use a private template — even when it is free', function () {
    $t = privTemplate();
    [$assigned, $siteA] = privAccount();
    [$other, $siteB] = privAccount();
    $admin = User::factory()->create(['is_super' => true]);

    TemplateAccess::assign($t, $assigned, $admin);

    // Assigned: listed as "Made for you", detail page opens, entitled.
    Livewire::actingAs($assigned)->test(MarketplaceStore::class, ['site' => $siteA])->assertSee('Made for you')->assertSee($t->name);
    Livewire::actingAs($assigned)->test(MarketplaceTemplatePage::class, ['site' => $siteA, 'slug' => $t->slug])->assertOk()->assertSee($t->name);
    expect(app(TemplateCommerce::class)->entitled($assigned, $t))->toBeTrue()
        ->and(TemplateAccess::canSee($assigned, $t, $siteA))->toBeTrue();

    // Everyone else: nothing, and no way in — not even as a "free" template.
    Livewire::actingAs($other)->test(MarketplaceStore::class, ['site' => $siteB])->assertDontSee($t->name);
    Livewire::actingAs($other)->test(MarketplaceTemplatePage::class, ['site' => $siteB, 'slug' => $t->slug])->assertNotFound();
    expect(app(TemplateCommerce::class)->entitled($other, $t))->toBeFalse()
        ->and(fn () => app(TemplateCommerce::class)->addFreeToLibrary($other, $t))->toThrow(HttpException::class)
        ->and(fn () => app(DesignService::class)->apply($other, $siteB, $t))->toThrow(HttpException::class);
});

test('a team member can use a template assigned to their account owner', function () {
    $t = privTemplate();
    [$owner, $site] = privAccount();
    TemplateAccess::assign($t, $owner, User::factory()->create(['is_super' => true]));
    $member = User::factory()->create();
    $site->members()->syncWithoutDetaching([$member->id => ['role' => 'admin']]);

    expect(TemplateAccess::canSee($member, $t, $site))->toBeTrue()
        ->and(TemplateAccess::canSee($member, $t))->toBeFalse(); // outside the account's site context
});

test('unassigning stops future use; the admin drawer assigns and removes by email', function () {
    $t = privTemplate();
    [$owner, $site] = privAccount();
    $admin = User::factory()->create(['is_super' => true]);

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)
        ->assertSet('edit.visibility', 'private')
        ->set('assignEmail', $owner->email)->call('assignAccount')
        ->assertHasNoErrors()
        ->assertSee($owner->email);
    expect(TemplateEntitlement::where('user_id', $owner->id)->where('template_id', $t->id)->value('source'))->toBe('granted');

    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)->call('unassignAccount', $owner->id);
    expect(TemplateAccess::canSee($owner, $t, $site))->toBeFalse()
        ->and(fn () => app(DesignService::class)->apply($owner, $site, $t))->toThrow(HttpException::class);

    // Unknown email is refused.
    Livewire::actingAs($admin)->test(PlatformTemplatesPage::class)
        ->call('startEdit', $t->id)->set('assignEmail', 'nobody-'.uniqid().'@example.test')->call('assignAccount')
        ->assertHasErrors('assignEmail');
});

test('private previews need a valid signed token', function () {
    $t = privTemplate(['slug' => 'priv-'.uniqid()]);
    $url = "/api/templates/preview/{$t->slug}";

    $this->getJson($url)->assertNotFound();
    $this->getJson($url.'?pt=9999999999.'.str_repeat('a', 32))->assertNotFound();               // forged
    $this->getJson($url.'?pt='.explode('.', TemplateAccess::previewToken($t->slug, -10))[0].'.x')->assertNotFound(); // expired/garbled
    expect(TemplateAccess::validPreviewToken($t->slug, TemplateAccess::previewToken($t->slug)))->toBeTrue()
        ->and(TemplateAccess::validPreviewToken('other-key', TemplateAccess::previewToken($t->slug)))->toBeFalse()
        ->and(TemplateAccess::validPreviewToken($t->slug, TemplateAccess::previewToken($t->slug, -1)))->toBeFalse();
});

test('the template id and price on the Use-template page cannot be tampered with', function () {
    $public = privTemplate(['visibility' => 'public', 'status' => 'published']);
    $private = privTemplate();
    [$owner] = privAccount();

    expect(fn () => Livewire::actingAs($owner)->test(TemplateDetailPage::class, ['templateKey' => $public->slug])->set('templateId', $private->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => Livewire::actingAs($owner)->test(TemplateDetailPage::class, ['templateKey' => $public->slug])->set('card.priceCents', 0))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('checkout success never saves a template onto someone else\'s site', function () {
    $t = privTemplate(['visibility' => 'public']);
    [$buyer] = privAccount();
    [, $victimSite] = privAccount();
    TemplateEntitlement::create(['user_id' => $buyer->id, 'template_id' => $t->id, 'source' => 'free']);

    $this->actingAs($buyer)->get('/templates/checkout/success?site='.$victimSite->name.'&template='.$t->uuid)
        ->assertRedirect(route('templates'));
    expect($victimSite->installedTemplates()->count())->toBe(0);
});

test('an admin upload published as public previews openly; client uploads and drafts still need a token', function () {
    $studio = TemplateCreator::firstOrCreate(['slug' => 'olux-studio'], ['name' => 'Olux Studio']);
    $mk = fn (array $a) => Template::create(array_merge([
        'uuid' => (string) Str::uuid(), 'name' => 'Up '.uniqid(), 'slug' => 'up-'.Str::lower(Str::random(8)), 'builtin_key' => 'u-'.Str::lower(Str::random(10)),
        'source' => 'upload', 'price_cents' => 0, 'currency' => 'gbp',
    ], $a));
    $store = $mk(['status' => 'published', 'visibility' => 'public', 'creator_id' => $studio->id]);
    $draft = $mk(['status' => 'draft', 'visibility' => 'public', 'creator_id' => $studio->id]);
    $client = $mk(['status' => 'private', 'visibility' => 'private', 'creator_id' => null]);

    expect(TemplateAccess::isPrivateKey($store->builtin_key))->toBeFalse()
        ->and(TemplateAccess::isPrivateKey($draft->builtin_key))->toBeTrue()
        ->and(TemplateAccess::isPrivateKey($client->builtin_key))->toBeTrue()
        ->and(TemplateAccess::isPrivateKey('u-unknownkey'))->toBeTrue();
});
