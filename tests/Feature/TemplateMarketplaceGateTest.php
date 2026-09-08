<?php

use App\Models\Template;
use App\Models\User;
use App\Services\TemplatePublisher;

function marketZip(): string
{
    $path = sys_get_temp_dir().'/tpl-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('template.json', json_encode([
        'name' => 'Gate Test '.uniqid(),
        'category' => 'Business',
        'pages' => [['name' => 'Home', 'url' => 'home', 'components' => []]],
    ]));
    $zip->close();

    return $path;
}

function templateMarketplaceGatePlanUser(string $plan): User
{
    $user = User::factory()->create();
    $user->currentSubscription()->update(['plan' => $plan, 'status' => 'active']);

    return $user;
}

test('publishing needs the Business plan; Business and moderators pass, Pro is refused', function () {
    $publisher = app(TemplatePublisher::class);

    expect(fn () => $publisher->publishFromZip(templateMarketplaceGatePlanUser('pro'), marketZip()))
        ->toThrow(RuntimeException::class, 'Business plan');

    $tpl = $publisher->publishFromZip(templateMarketplaceGatePlanUser('business'), marketZip());
    expect($tpl->status)->toBe('draft');

    config(['templates.moderators' => [$mod = 'mod-'.uniqid().'@example.com']]);
    $moderator = User::factory()->create(['email' => $mod]); // stays on trial
    expect($publisher->publishFromZip($moderator, marketZip()))->toBeInstanceOf(Template::class);
});

test('submit is gated too — a creator downgraded after drafting cannot enter review', function () {
    $publisher = app(TemplatePublisher::class);
    $creator = templateMarketplaceGatePlanUser('business');
    $tpl = $publisher->publishFromZip($creator, marketZip());

    $creator->currentSubscription()->update(['plan' => 'starter']);
    expect(fn () => $publisher->submit($tpl->fresh()))->toThrow(RuntimeException::class);
    expect($tpl->fresh()->status)->toBe('draft');

    $creator->currentSubscription()->update(['plan' => 'business']);
    $publisher->submit($tpl->fresh());
    expect($tpl->fresh()->status)->toBe('in_review');
});

test('new templates are priced in gbp and the sync command still runs without a user', function () {
    $tpl = app(TemplatePublisher::class)->publishFromZip(templateMarketplaceGatePlanUser('business'), marketZip());
    expect($tpl->currency)->toBe('gbp')
        ->and($tpl->priceLabel())->not->toContain('$');

    $this->artisan('templates:sync')->assertSuccessful();
});
