<?php

namespace App\Actions\Debts;

use App\Models\Debt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteDebt
{
    public function execute(Debt $debt): void
    {
        DB::transaction(function () use ($debt): void {
            if ($debt->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'debt' => 'Нельзя удалить долг: по нему есть возвраты.',
                ]);
            }

            $debt->delete();
        });
    }
}
