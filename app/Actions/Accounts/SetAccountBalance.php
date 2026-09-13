<?php

namespace App\Actions\Accounts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Support\Facades\DB;

class SetAccountBalance
{
    public function execute(Account $account, User $actor, int $newBalance, string $occurredOn): ?Transaction
    {
        return DB::transaction(function () use ($account, $actor, $newBalance, $occurredOn) {
            $account->assertActive();

            $delta = $newBalance - AccountBalance::for($account);

            if ($delta === 0) {
                return null;
            }

            return Transaction::query()->create([
                'workspace_id' => $account->workspace_id,
                'type' => TransactionType::Adjustment,
                'amount' => $delta,
                'occurred_on' => $occurredOn,
                'account_id' => $account->id,
                'user_id' => $actor->id,
            ]);
        });
    }
}
