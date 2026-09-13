<?php

namespace App\Actions\Recurrences;

use App\Actions\Recurrences\Concerns\PreparesRecurrenceData;
use App\Models\Recurrence;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class UpdateRecurrence
{
    use PreparesRecurrenceData;

    /**
     * @param  array{type: string, account_id: int, counterparty_account_id: int|null, category_id: int|null, amount: int, description?: string|null, frequency: string, next_occurred_on: string}  $data
     */
    public function execute(Recurrence $recurrence, array $data): Recurrence
    {
        return DB::transaction(function () use ($recurrence, $data) {
            $workspace = Workspace::query()->findOrFail($recurrence->workspace_id);
            $fields = $this->prepared($workspace, $data);

            $recurrence->update([
                'type' => $fields['type'],
                'account_id' => $fields['account_id'],
                'counterparty_account_id' => $fields['counterparty_account_id'],
                'category_id' => $fields['category_id'],
                'amount' => $fields['amount'],
                'description' => $fields['description'],
                'frequency' => $fields['frequency'],
                'next_occurred_on' => $fields['next_occurred_on'],
            ]);

            return $recurrence;
        });
    }
}
