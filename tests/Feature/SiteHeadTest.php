<?php

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteHead;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// Site Names are unique across sites: keep each test's sites out of the next run.
uses(DatabaseTransactions::class);

beforeEach(fn () => config(['publishing.subdomain_base' => 'sites.test']));

function headSite(array $values = [], array $attrs = []): Site
{
    $owner = User::factory()->create();
    $name = 'hd-'.substr(uniqid(), -8);
    $site = Site::create($attrs + ['user_id' => $owner->id, 'name' => $name, 'domain' => $name.'.example', 'owner' => 'x', 'description' => 't']);
    SiteProperties::save($site, ['values' => $values]);

    return $site->fresh();
}

test('live pages get icons, SEO defaults, schema.org and analytics from Site Properties', function () {
    $site = headSite([
        'site_name' => 'Grace Way', 'meta_description' => 'Sunday services in Blackburn', 'business_type' => 'Church',
        'share_image' => 'https://cdn.example/share.jpg', 'search_console_token' => 'abcDEF123456', 'ga4_id' => 'G-ABC1234',
        'address_town' => 'Blackburn',
    ], ['live' => true]);
    $site->setAttr('site.icons', json_encode(['32' => '/storage/i32.png', '180' => '/storage/i180.png', '192' => '/storage/i192.png', '512' => '/storage/i512.png']));

    $html = SiteHead::head($site, Request::create("http://{$site->name}.sites.test/about"));
    expect($html)
        ->toContain('<link rel="icon" type="image/png" sizes="32x32"')
        ->toContain('<link rel="apple-touch-icon" sizes="180x180"')
        ->toContain('<link rel="manifest" href="/site.webmanifest">')
        ->toContain('content="Sunday services in Blackburn"')
        ->toContain('og:image" content="https://cdn.example/share.jpg"')
        ->toContain('google-site-verification" content="abcDEF123456"')
        ->toContain('<link rel="canonical" href="http://'.$site->name.'.sites.test/about">')
        ->toContain('"@type":"Church"')
        ->toContain('googletagmanager.com/gtag/js?id=G-ABC1234')
        ->toContain('window.__oluxTrack();')
        ->not->toContain('noindex');
});

test('values edited without validation are re-checked, and the JSON-LD cannot break out of its script', function () {
    $site = headSite(['site_name' => 'Evil</script><script>alert(1)</script>', 'ga4_id' => 'G-1"</script>', 'search_console_token' => '"><script>'], ['live' => true]);

    $html = SiteHead::head($site, Request::create("http://{$site->name}.sites.test/"));
    expect($html)->not->toContain('<script>alert(1)')
        ->not->toContain('googletagmanager')
        ->not->toContain('google-site-verification');
});

test('cookie consent holds analytics until accepted; custom scripts need a premium plan', function () {
    $site = headSite(['ga4_id' => 'G-ABC1234', 'cookie_consent' => '1', 'cookie_message' => 'We bake cookies'], ['live' => true]);
    $site->setAttr('site.custom_head', '<script>window.custom=1</script>');
    $site->user->currentSubscription()->update(['plan' => 'starter', 'status' => 'active']); // no premium features
    $site = $site->fresh();

    $head = SiteHead::head($site, Request::create("http://{$site->name}.sites.test/"));
    expect($head)->toContain('localStorage.getItem("olux-consent")==="yes"')
        ->not->toContain('window.__oluxTrack();')
        ->not->toContain('window.custom=1');
    expect(SiteHead::bodyEnd($site))->toContain('We bake cookies')->toContain('id="olux-consent"');

    $site->user->currentSubscription()->update(['plan' => 'pro', 'status' => 'active']);
    expect(SiteHead::head($site->fresh(), Request::create("http://{$site->name}.sites.test/")))->toContain('window.custom=1');
});

test('a site that is not live stays out of search engines while "noindex while draft" is on', function () {
    $site = headSite([], ['live' => false]);
    $req = Request::create("http://{$site->name}.sites.test/");

    expect(SiteHead::head($site, $req))->toContain('<meta name="robots" content="noindex, nofollow">')
        ->and(SiteHead::robots($site, $req))->toBe("User-agent: *\nDisallow: /\n");

    SiteProperties::save($site, ['values' => ['noindex_while_draft' => '0']]);
    expect(SiteHead::head($site->fresh(), $req))->not->toContain('noindex');
});

test('the live host serves robots.txt, sitemap.xml and the web manifest', function () {
    $site = headSite(['site_name' => 'Grace Way', 'short_name' => 'Grace'], ['live' => true]);
    Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    Page::create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about', 'keywords' => '', 'is_published' => true]);
    Page::create(['site_id' => $site->id, 'name' => 'Draft', 'url' => '/draft', 'keywords' => '', 'is_published' => false]);
    $base = "http://{$site->name}.sites.test";

    $this->get("$base/robots.txt")->assertOk()->assertSee('Sitemap: '.$base.'/sitemap.xml', false);
    $this->get("$base/sitemap.xml")->assertOk()
        ->assertSee("<loc>$base/about</loc>", false)
        ->assertDontSee('/draft', false);
    $this->get("$base/site.webmanifest")->assertOk()->assertJsonPath('name', 'Grace Way')->assertJsonPath('short_name', 'Grace');
});

test('maintenance mode shows a 503 "back soon" page on the live host only', function () {
    $site = headSite(['site_name' => 'Grace Way', 'maintenance' => '1', 'maintenance_message' => 'Painting the walls', 'email' => 'hi@gw.test'], ['live' => true]);

    $this->get("http://{$site->name}.sites.test/")
        ->assertStatus(503)
        ->assertHeader('Retry-After', '3600')
        ->assertSee('Painting the walls')
        ->assertSee('hi@gw.test');

    // The team's preview on the platform host is unaffected.
    $this->get('http://localhost/login')->assertOk()->assertDontSee('Painting the walls');
});

test('custom domains redirect to the preferred host (www only once it resolves)', function () {
    $d = 'grace'.substr(uniqid(), -8).'.org'; // unique: the testing DB persists between runs
    $site = headSite([], ['live' => true, 'domain' => $d, 'domain_verified_at' => now()]);

    $this->get("http://www.$d/about?x=1")->assertRedirect("http://$d/about?x=1")->assertStatus(301);

    SiteProperties::save($site, ['values' => ['canonical_host' => 'www']]);
    Cache::put("edge-resolves:www.$d", false, 60);
    expect(SiteHead::canonicalRedirect($site->fresh(), Request::create("http://$d/")))->toBeNull();
    Cache::put("edge-resolves:www.$d", true, 60);
    expect(SiteHead::canonicalRedirect($site->fresh(), Request::create("http://$d/")))->toBe("http://www.$d/");
});

test('a page\'s own meta tags (Pages › page › Page attributes & meta tags) override the site defaults on that page only', function () {
    $site = headSite(['site_name' => 'Grace Way', 'meta_description' => 'Sunday services in Blackburn', 'share_image' => 'https://cdn.example/share.jpg'], ['live' => true]);
    $about = Page::create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about', 'keywords' => 'church, about', 'is_published' => true]);
    $about->setAttr('title', 'About Grace Way — $50 welcome');
    $about->setAttr('description', 'Who we are and what we believe.');
    $about->setAttr('og_image', 'https://cdn.example/about.jpg');
    $about->setAttr('robots', 'noindex, follow');
    $about->setAttr('canonical_url', 'https://gracechurch.example/about');

    $html = SiteHead::head($site, Request::create("http://{$site->name}.sites.test/about"));
    expect($html)
        ->toContain('<meta name="description" content="Who we are and what we believe.">')
        ->toContain('og:title" content="About Grace Way — $50 welcome"')
        ->toContain('og:image" content="https://cdn.example/about.jpg"')
        ->toContain('<meta name="keywords" content="church, about">')
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->toContain('<link rel="canonical" href="https://gracechurch.example/about">')
        ->not->toContain('Sunday services in Blackburn');
    expect(SiteHead::pageMeta($site, Request::create("http://{$site->name}.sites.test/about"))['title'])->toBe('About Grace Way — $50 welcome');

    // Another page keeps the site defaults.
    $home = SiteHead::head($site, Request::create("http://{$site->name}.sites.test/"));
    expect($home)->toContain('content="Sunday services in Blackburn"')->toContain('og:image" content="https://cdn.example/share.jpg"')
        ->not->toContain('noindex');
});
