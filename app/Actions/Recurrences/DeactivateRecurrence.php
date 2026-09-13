<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;
use Illuminate\Support\Facades\DB;

class DeactivateRecurrence
{
    public function execute(Recurrence $recurrence): Recurrence
    {
        return DB::transaction(function () use ($recurrence) {
            $recurrence->update([
                'is_active' => false,
            ]);

            return $recurrence;
        });
    }
}
