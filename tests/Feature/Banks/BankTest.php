<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Banks\CreateBank;
use App\Actions\Banks\DeleteBank;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('creates a bank via http', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->post(route('banks.store'), ['name' => 'Тинькофф', 'color' => '#1C6CFF'])
        ->assertRedirect(route('accounts.index'));

    $this->assertDatabaseHas('banks', ['name' => 'Тинькофф', 'color' => '#1C6CFF']);
});

it('refuses to delete a bank that still has accounts', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $bank = (new CreateBank)->execute($workspace, ['name' => 'Тинькофф']);

    (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => $bank->id,
        'user_id' => null,
        'opening_balance' => 0,
    ]);

    expect(fn () => (new DeleteBank)->execute($bank))
        ->toThrow(ValidationException::class);
});
