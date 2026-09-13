<?php

namespace App\Actions\Goals;

use App\Models\Goal;
use Illuminate\Support\Facades\DB;

class UpdateGoal
{
    /**
     * @param  array{name: string, target_amount: int, target_date: string|null, notes: string|null}  $data
     */
    public function execute(Goal $goal, array $data): Goal
    {
        return DB::transaction(function () use ($goal, $data) {
            $goal->update([
                'name' => $data['name'],
                'target_amount' => $data['target_amount'],
                'target_date' => $data['target_date'],
                'notes' => $data['notes'],
            ]);

            return $goal;
        });
    }
}
