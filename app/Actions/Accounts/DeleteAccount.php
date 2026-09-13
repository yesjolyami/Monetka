<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function execute(Account $account): void
    {
        DB::transaction(function () use ($account): void {
            $hasTransactions = Transaction::query()
                ->where(function ($query) use ($account): void {
                    $query->where('account_id', $account->id)
                        ->orWhere('counterparty_account_id', $account->id);
                })
                ->exists();

            if ($hasTransactions) {
                throw ValidationException::withMessages([
                    'account' => 'Нельзя удалить счёт: по нему есть операции.',
                ]);
            }

            $account->delete();
        });
    }
}
