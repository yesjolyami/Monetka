<?php

use App\Enums\Appearance;
use App\Models\User;

it('defaults a new user to system', function () {
    expect(User::factory()->create()->fresh()->theme)->toBe(Appearance::System);
});

it('persists the appearance on the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('theme.update'), ['theme' => 'light'])
        ->assertRedirect();

    expect($user->fresh()->theme)->toBe(Appearance::Light);

    $this->put(route('theme.update'), ['theme' => 'dark'])
        ->assertRedirect();

    expect($user->fresh()->theme)->toBe(Appearance::Dark);
});
