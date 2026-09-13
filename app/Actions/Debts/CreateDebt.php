<?php

namespace App\Actions\Debts;

use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateDebt
{
    /**
     * @param  array{direction: DebtDirection|string, counterparty_name: string, original_amount: int, notes: string|null}  $data
     */
    public function execute(Workspace $workspace, array $data): Debt
    {
        return DB::transaction(function () use ($workspace, $data) {
            return $workspace->debts()->create([
                'direction' => $data['direction'],
                'counterparty_name' => $data['counterparty_name'],
                'original_amount' => $data['original_amount'],
                'notes' => $data['notes'],
            ]);
        });
    }
}
