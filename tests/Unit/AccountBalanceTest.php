<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sums the ledger with spec signs', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = Account::factory()->create(['workspace_id' => $workspace->id]);
    $to = Account::factory()->create(['workspace_id' => $workspace->id]);

    Transaction::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => TransactionType::Income,
        'account_id' => $from->id,
        'amount' => 1000,
    ]);
    Transaction::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => TransactionType::Expense,
        'account_id' => $from->id,
        'amount' => 200,
    ]);
    Transaction::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => TransactionType::Transfer,
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 100,
    ]);
    Transaction::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => TransactionType::Adjustment,
        'account_id' => $from->id,
        'amount' => -50,
    ]);

    expect(AccountBalance::for($from->fresh()))->toBe(1000 - 200 - 100 - 50)
        ->and(AccountBalance::for($to->fresh()))->toBe(100);
});
