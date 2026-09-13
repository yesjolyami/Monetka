<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class UpdateAccount
{
    /**
     * @param  array{name: string, type: string, bank_id?: int|null, user_id?: int|null}  $data
     */
    public function execute(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data) {
            $account->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'bank_id' => $data['bank_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
            ]);

            return $account;
        });
    }
}
