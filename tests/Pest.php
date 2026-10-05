<?php

use App\Models\ApiToken;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Mint a hashed, site-scoped Site Connect api_token for tests and return the raw
 * bearer value. Shared across the Site Connect suites.
 */
function connectToken(Site $site, array $abilities = ['connect:ingest', 'content:read']): string
{
    $raw = 'olx_live_'.Str::random(40);
    ApiToken::create([
        'user_id' => $site->user_id,
        'site_id' => $site->id,
        'name' => 'test',
        'token' => hash('sha256', $raw),
        'token_preview' => substr($raw, 0, 12),
        'abilities' => $abilities,
    ]);

    return $raw;
}

/**
 * Publish a first-party template (resources/templates/{key}) in the catalog,
 * the way Admin › Templates does — the public gallery and /designs pages only
 * show published + public catalog rows. Idempotent across test runs.
 */
function publishBuiltinTemplate(string $key = 'verita'): Template
{
    $tpl = Template::where('builtin_key', $key)->first();
    if ($tpl) {
        $tpl->update(['status' => 'published', 'visibility' => 'public']);

        return $tpl->fresh();
    }

    return Template::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'name' => Str::headline($key),
        'slug' => Template::where('slug', $key)->exists() ? $key.'-'.uniqid() : $key,
        'description' => 'A ready-made '.Str::headline($key).' design.',
        'short_description' => 'A ready-made '.Str::headline($key).' design',
        'category' => 'Business', 'status' => 'published', 'visibility' => 'public',
        'price_cents' => 0, 'currency' => 'gbp', 'source' => 'builtin', 'builtin_key' => $key, 'published_at' => now(),
    ]);
}
