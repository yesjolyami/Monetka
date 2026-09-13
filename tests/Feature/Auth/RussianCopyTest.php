<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

it('shows russian on the login page', function () {
    $this->get(route('login'))->assertSee('Вход', false);
});

it('shows a russian landing instead of the kit welcome', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'))
        ->assertDontSee('Let\'s get started', false);
});

it('still authenticates guests through the login form', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

it('shares the current workspace on the overview', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('workspace'));
});
