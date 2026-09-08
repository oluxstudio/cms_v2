<?php

use App\Livewire\SignupWizard;
use App\Livewire\TemplateBuyPage;
use App\Livewire\TemplateDetailPage;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplatePublisher;
use App\Support\TemplateCards;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

function detailTemplate(array $extra = []): Template
{
    return Template::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'name' => 'Detail '.uniqid(), 'slug' => 'det-'.uniqid(),
        'description' => 'Detail page test design', 'category' => 'Business',
        'status' => 'published', 'price_cents' => 0, 'currency' => 'gbp',
        'source' => 'creator', 'published_at' => now(),
    ], $extra));
}

test('guests see the detail page (curated and catalog), drafts 404, and Get sends them to register', function () {
    $tpl = detailTemplate();
    $this->get('/designs/curated-verita')->assertOk()->assertSee('Verita')->assertSee('View template')->assertSee('Use template');
    $this->get('/designs/'.$tpl->slug)->assertOk()->assertSee($tpl->name);
    $draft = detailTemplate(['status' => 'draft']);
    $this->get('/designs/'.$draft->slug)->assertNotFound();

    Livewire::test(TemplateDetailPage::class, ['templateKey' => $tpl->slug])
        ->call('getTemplate')
        ->assertRedirect(route('login', ['mode' => 'register']));
    expect(session('url.intended'))->toBe(route('template.detail', $tpl->slug));
});

test('Use template creates a fresh site for the design — existing sites are never touched', function () {
    $owner = User::factory()->create();
    $owner->currentSubscription()->update(['plan' => 'business', 'status' => 'active']);
    $existing = Site::create(['user_id' => $owner->id, 'name' => 'det-'.uniqid(), 'domain' => 'det-'.uniqid().'.test', 'owner' => 'x', 'description' => 't', 'template' => 'blank']);
    $tpl = detailTemplate();

    $c = Livewire::actingAs($owner)->test(TemplateDetailPage::class, ['templateKey' => $tpl->slug])->call('getTemplate');

    $new = Site::where('user_id', $owner->id)->whereKeyNot($existing->id)->latest('id')->first();
    expect($new)->not->toBeNull()
        ->and($new->installedTemplates()->where('template_id', $tpl->id)->first()?->isApplied())->toBeTrue()
        ->and($new->members()->whereKey($owner->id)->exists())->toBeTrue()
        ->and($existing->fresh()->template)->toBe('blank'); // untouched
    $c->assertRedirect(url($new->name.'/connect'));

    // A zero-site owner gets a site too — no wizard detour.
    $fresh = User::factory()->create();
    Livewire::actingAs($fresh)->test(TemplateDetailPage::class, ['templateKey' => 'curated-verita'])
        ->call('getTemplate')->assertRedirect();
    expect(Site::where('user_id', $fresh->id)->count())->toBe(1);

    // Plan full → clear message, no site created.
    $limited = User::factory()->create(); // trial: 1 site max
    Site::create(['user_id' => $limited->id, 'name' => 'lim-'.uniqid(), 'domain' => 'lim-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    Livewire::actingAs($limited)->test(TemplateDetailPage::class, ['templateKey' => 'curated-verita'])
        ->call('getTemplate')->assertSee('no room for another site');
    expect(Site::where('user_id', $limited->id)->count())->toBe(1);
});

test('a priced template routes Get to the buy page, which shows the price and needs Connect to pay', function () {
    $owner = User::factory()->create();
    Site::create(['user_id' => $owner->id, 'name' => 'buy-'.uniqid(), 'domain' => 'buy-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $tpl = detailTemplate(['price_cents' => 4500]);

    Livewire::actingAs($owner)->test(TemplateDetailPage::class, ['templateKey' => $tpl->slug])
        ->call('getTemplate')->assertRedirect(route('template.buy', $tpl->slug));

    config(['services.stripe_platform.secret' => null]);
    $this->actingAs($owner)->get('/designs/'.$tpl->slug.'/buy')->assertOk()->assertSee('£45');
    Livewire::actingAs($owner)->test(TemplateBuyPage::class, ['templateKey' => $tpl->slug])
        ->call('buyNow')
        ->assertSee('marketplace payments');
});

test('a design chosen before signup lands on the site created by the wizard', function () {
    Mail::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    session(['pending_template' => 'curated-verita']);
    Livewire::actingAs($user)->test(SignupWizard::class)
        ->set('step', 2)->set('type', 'salon')->set('business', 'Pending Tpl '.uniqid())
        ->call('createSite')->assertHasNoErrors();

    $site = Site::where('user_id', $user->id)->latest('id')->first();
    expect($site->installedTemplates()->where('builtin_key', 'verita')->exists())->toBeTrue();
});

test('the detail page carries slideshow screenshots, specs and the accordion', function () {
    // Curated: real generated screenshots + specs.
    $this->get('/designs/curated-verita')->assertOk()
        ->assertSee('template-screenshots', false) // URLs are JSON-escaped inside the Alpine payload
        ->assertSee('Product specs')->assertSee('Framework')->assertSee('Nuxt 4 app')
        ->assertSee('Added to sites')->assertSee("What's inside", false)->assertSee('About');

    // Curated installs = saved-to-site rows for that app key.
    $card = TemplateCards::resolve('curated-verita')[0];
    expect($card['screenshots'])->toHaveCount(3)
        ->and($card['framework'])->toBe('Nuxt 4 app')
        ->and($card['installs'])->toBe(SiteTemplate::where('builtin_key', 'verita')->count());

    // Catalog with only a thumbnail → single-image fallback, no rail markup needed.
    $tpl = detailTemplate(['thumbnail_url' => 'https://cdn.example/x.png']);
    $card = TemplateCards::resolve($tpl->slug)[0];
    expect($card['screenshots'])->toBe(['https://cdn.example/x.png'])
        ->and($card['framework'])->toBe('Olux block renderer')
        ->and($card['createdAt'])->not->toBeNull();
});

test('creator zips can ship screenshot-*.png files that land in the version payload', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $path = sys_get_temp_dir().'/shot-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('template.json', json_encode(['name' => 'Shots '.uniqid(), 'category' => 'Business', 'pages' => [['name' => 'Home', 'url' => 'home', 'components' => []]]]));
    $zip->addFromString('screenshot-1.png', $png);
    $zip->addFromString('screenshot-2.png', $png);
    $zip->close();

    $creator = User::factory()->create();
    $creator->currentSubscription()->update(['plan' => 'business', 'status' => 'active']);
    $tpl = app(TemplatePublisher::class)->publishFromZip($creator, $path);

    $shots = $tpl->latestVersion->payload['screenshots'] ?? [];
    expect($shots)->toHaveCount(2)
        ->and($shots[0])->toContain('screenshots/screenshot-1.png');
});

test('using a curated template creates a new site bound to it and opens its connect page', function () {
    $owner = User::factory()->create();
    $owner->currentSubscription()->update(['plan' => 'enterprise', 'status' => 'active']);
    Site::create(['user_id' => $owner->id, 'name' => 'cn-'.uniqid(), 'domain' => 'cn-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    Site::create(['user_id' => $owner->id, 'name' => 'cn2-'.uniqid(), 'domain' => 'cn2-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);

    // Even with several existing sites: no picker — a fresh site, straight to connect.
    $c = Livewire::actingAs($owner)->test(TemplateDetailPage::class, ['templateKey' => 'curated-hairco'])
        ->call('getTemplate');

    $new = Site::where('user_id', $owner->id)->latest('id')->first();
    expect($new->name)->toStartWith('hairco') // 'hairco' itself is a reserved subdomain → uniquified
        ->and($new->fresh()->template)->toBe('hairco')
        ->and($new->installedTemplates()->first()?->isApplied())->toBeTrue()
        ->and(Site::where('user_id', $owner->id)->count())->toBe(3);
    $c->assertRedirect(url($new->name.'/connect'));
    expect(Site::validSubdomainLabel($new->name))->toBeTrue();
});
