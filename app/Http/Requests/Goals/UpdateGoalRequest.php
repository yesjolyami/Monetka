<?php

namespace App\Http\Requests\Goals;

use App\Models\Goal;

class UpdateGoalRequest extends StoreGoalRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof Goal
            && $this->user()?->can('update', $goal);
    }
}
