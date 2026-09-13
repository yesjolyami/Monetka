<?php

namespace App\Actions\Goals;

use App\Models\Goal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteGoal
{
    public function execute(Goal $goal): void
    {
        DB::transaction(function () use ($goal): void {
            if ($goal->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'goal' => 'Нельзя удалить цель: по ней есть операции.',
                ]);
            }

            $goal->delete();
        });
    }
}
