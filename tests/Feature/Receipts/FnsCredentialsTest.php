<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

function fakeFnsAuth(bool $ok = true): void
{
    config(['services.fns.base_url' => 'https://irkkt-mobile.nalog.ru:8888']);

    Http::fake([
        'https://irkkt-mobile.nalog.ru:8888/v2/mobile/users/lkfl/auth' => $ok
            ? Http::response(['sessionId' => 'sess-1'], 200)
            : Http::response(['message' => 'Unauthorized'], 401),
    ]);
}

it('asks for fns credentials on the first visit', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('receipts/Index')
            ->where('connected', false)
            ->where('fnsInn', null)
            ->missing('fns_password')
            ->missing('configured'));
});

it('connects the fns cabinet when credentials are valid', function () {
    fakeFnsAuth();

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->post(route('receipts.connect'), [
            'inn' => '7700000000',
            'password' => 'secret',
        ])
        ->assertRedirect(route('receipts.index'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->fns_inn)->toBe('7700000000')
        ->and($user->fns_password)->toBe('secret');

    $this->actingAs($user)
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('connected', true)
            ->where('fnsInn', '7700000000'));
});

it('rejects invalid fns credentials without saving them', function () {
    fakeFnsAuth(ok: false);

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->post(route('receipts.connect'), [
            'inn' => '7700000000',
            'password' => 'wrong',
        ])
        ->assertRedirect(route('receipts.index'))
        ->assertSessionHasErrors('password');

    expect($user->fresh()->fns_inn)->toBeNull()
        ->and($user->fresh()->fns_password)->toBeNull();
});

it('checks the stored fns login in the background', function () {
    fakeFnsAuth();

    $user = User::factory()->create([
        'fns_inn' => '7700000000',
        'fns_password' => 'secret',
    ]);
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->getJson(route('receipts.status'))
        ->assertOk()
        ->assertJson([
            'connected' => true,
            'ok' => true,
        ]);
});

it('reports when stored fns credentials no longer work', function () {
    fakeFnsAuth(ok: false);

    $user = User::factory()->create([
        'fns_inn' => '7700000000',
        'fns_password' => 'old-secret',
    ]);
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->getJson(route('receipts.status'))
        ->assertOk()
        ->assertJson([
            'connected' => true,
            'ok' => false,
        ]);
});

it('reports disconnected status when credentials are missing', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->getJson(route('receipts.status'))
        ->assertOk()
        ->assertJson([
            'connected' => false,
            'ok' => false,
        ]);
});
