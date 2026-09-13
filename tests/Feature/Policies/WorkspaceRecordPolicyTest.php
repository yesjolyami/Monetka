<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;

it('reports workspace membership', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $foreign = (new CreateWorkspace)->execute(User::factory()->create(), 'Чужой', 'RUB');

    expect($user->belongsToWorkspace($workspace))->toBeTrue()
        ->and($user->belongsToWorkspace($foreign))->toBeFalse();
});

it('returns 403 when a stranger views a foreign account', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    (new CreateWorkspace)->execute($stranger, 'Другой', 'RUB');
    $account = (new CreateAccount)->execute($ws, $owner, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);

    $this->actingAs($stranger)
        ->get(route('accounts.show', $account))
        ->assertForbidden();
});
