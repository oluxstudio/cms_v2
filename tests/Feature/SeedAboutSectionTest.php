<?php

use App\Models\Collection;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;

test('cms:seed-about seeds attributes + collections idempotently', function () {
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'about-'.uniqid(),
        'domain' => 'about.test', 'owner' => $owner->name, 'description' => 't',
    ]);
    Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);

    $this->artisan('cms:seed-about', ['site' => $site->name])->assertExitCode(0);

    // Home-page attributes populated.
    $home = $site->pages()->where('url', '/')->first();
    expect($home->getAttr('about_intro_headline'))->toBe('Turning Bright Ideas into')
        ->and($home->getAttr('about_mission'))->not->toBeEmpty();

    // Four collections with the expected slugs + counts.
    $counts = fn () => Collection::where('site_id', $site->id)
        ->whereIn('slug', ['stats', 'team', 'faq', 'collage'])->withCount('items')
        ->get()->mapWithKeys(fn ($c) => [$c->slug => $c->items_count]);
    expect($counts()->all())->toMatchArray(['stats' => 4, 'team' => 3, 'faq' => 12, 'collage' => 12]);

    // Re-running does not duplicate collections or items.
    $this->artisan('cms:seed-about', ['site' => $site->name])->assertExitCode(0);
    expect(Collection::where('site_id', $site->id)->whereIn('slug', ['stats', 'team', 'faq', 'collage'])->count())->toBe(4)
        ->and($counts()->all())->toMatchArray(['stats' => 4, 'team' => 3, 'faq' => 12, 'collage' => 12]);
});

test('cms:seed-about fails cleanly for an unknown site', function () {
    $this->artisan('cms:seed-about', ['site' => 'no-such-site'])->assertExitCode(1);
});
