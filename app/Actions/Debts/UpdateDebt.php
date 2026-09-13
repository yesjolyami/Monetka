<?php

namespace App\Actions\Debts;

use App\Enums\DebtDirection;
use App\Models\Debt;
use Illuminate\Support\Facades\DB;

class UpdateDebt
{
    /**
     * @param  array{direction: DebtDirection|string, counterparty_name: string, original_amount: int, notes: string|null}  $data
     */
    public function execute(Debt $debt, array $data): Debt
    {
        return DB::transaction(function () use ($debt, $data) {
            $debt->update([
                'direction' => $data['direction'],
                'counterparty_name' => $data['counterparty_name'],
                'original_amount' => $data['original_amount'],
                'notes' => $data['notes'],
            ]);

            return $debt;
        });
    }
}
