<?php

namespace App\Http\Requests\Recurrences;

use App\Models\Recurrence;

class UpdateRecurrenceRequest extends StoreRecurrenceRequest
{
    public function authorize(): bool
    {
        $recurrence = $this->route('recurrence');

        return $recurrence instanceof Recurrence
            && $this->user()?->can('update', $recurrence);
    }
}
