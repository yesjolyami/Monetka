<?php

namespace App\Actions\Accounts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateAccount
{
    /**
     * @param  array{name: string, type: string, bank_id: int|null, user_id: int|null, opening_balance: int}  $data
     */
    public function execute(Workspace $workspace, User $actor, array $data): Account
    {
        return DB::transaction(function () use ($workspace, $actor, $data) {
            $account = Account::query()->create([
                'workspace_id' => $workspace->id,
                'bank_id' => $data['bank_id'],
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'type' => $data['type'],
            ]);

            $openingBalance = $data['opening_balance'];

            if ($openingBalance !== 0) {
                Transaction::query()->create([
                    'workspace_id' => $workspace->id,
                    'type' => TransactionType::Adjustment,
                    'amount' => $openingBalance,
                    'occurred_on' => now()->toDateString(),
                    'account_id' => $account->id,
                    'user_id' => $actor->id,
                ]);
            }

            return $account;
        });
    }
}
