<?php

namespace App\Actions\Banks;

use App\Models\Bank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBank
{
    public function execute(Bank $bank): void
    {
        DB::transaction(function () use ($bank): void {
            if ($bank->accounts()->exists()) {
                throw ValidationException::withMessages([
                    'bank' => 'Нельзя удалить банк: к нему привязаны счета.',
                ]);
            }

            $bank->delete();
        });
    }
}
