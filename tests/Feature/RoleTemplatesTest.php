<?php

use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;

function roleSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'roles-'.uniqid(), 'domain' => 'roles-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);

    return [$owner, $site];
}

test('accounts get the vertical role templates, including previously seeded accounts', function () {
    [$owner] = roleSite();
    // Simulate an account seeded before the templates existed.
    Role::forAccount($owner);
    Role::where('account_id', $owner->id)->whereIn('slug', ['manager', 'staff', 'accountant', 'content-editor'])->delete();

    $slugs = Role::forAccount($owner->fresh())->pluck('slug');
    expect($slugs)->toContain('admin', 'editor', 'viewer', 'manager', 'staff', 'accountant', 'content-editor');
});

test('staff sees bookings but not pages; accountant sees invoices but not team; manager cannot manage the team', function () {
    [$owner, $site] = roleSite();
    $site->enableFeature('bookings');
    $site->enableFeature('invoices');
    $roles = Role::forAccount($owner);
    $grant = function (string $slug) use ($owner, $roles, $site) {
        $u = User::factory()->create();
        AccountMember::create(['account_id' => $owner->id, 'user_id' => $u->id, 'role_id' => $roles->firstWhere('slug', $slug)->id, 'site_id' => $site->id]);

        return $u;
    };

    $staff = $grant('staff');
    $this->actingAs($staff)->get("/{$site->name}/bookings")->assertOk();
    $this->actingAs($staff)->get("/{$site->name}/pages")->assertForbidden();

    $accountant = $grant('accountant');
    $this->actingAs($accountant)->get("/{$site->name}/invoices")->assertOk();
    $this->actingAs($accountant)->get("/{$site->name}/team")->assertForbidden();

    $manager = $grant('manager');
    $this->actingAs($manager)->get("/{$site->name}/pages")->assertOk();
    $this->actingAs($manager)->get("/{$site->name}/team")->assertForbidden();
});
