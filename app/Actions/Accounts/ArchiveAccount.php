<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class ArchiveAccount
{
    public function execute(Account $account): Account
    {
        return DB::transaction(function () use ($account) {
            $account->forceFill(['archived_at' => now()])->save();

            return $account;
        });
    }
}
