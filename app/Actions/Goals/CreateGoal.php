<?php

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateGoal
{
    /**
     * @param  array{name: string, target_amount: int, target_date: string|null, notes: string|null}  $data
     */
    public function execute(Workspace $workspace, array $data): Goal
    {
        return DB::transaction(function () use ($workspace, $data) {
            return $workspace->goals()->create([
                'name' => $data['name'],
                'target_amount' => $data['target_amount'],
                'target_date' => $data['target_date'],
                'notes' => $data['notes'],
            ]);
        });
    }
}
