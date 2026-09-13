<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTransaction
{
    public function execute(Transaction $tx): void
    {
        if ($tx->type->isLedgerLocked()) {
            throw ValidationException::withMessages([
                'transaction' => 'Эту операцию нельзя удалить. Для баланса счёта используйте «Выставить баланс».',
            ]);
        }

        DB::transaction(function () use ($tx): void {
            $tx->delete();
        });
    }
}
