<?php

declare(strict_types=1);

use App\Models\User;

it('keeps the bottom navigation above the device navigation area', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('style="padding-top: var(--app-safe-top);"', false)
        ->assertSee('style="padding-bottom: var(--app-bottom-nav-offset);"', false)
        ->assertSee(
            'style="bottom: var(--app-nav-bottom-offset); padding-left: var(--app-safe-left); padding-right: var(--app-safe-right);"',
            false,
        );
});
