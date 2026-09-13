<?php

namespace App\Http\Requests\Recurrences;

use App\Models\Recurrence;
use Illuminate\Foundation\Http\FormRequest;

class PostRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recurrence = $this->route('recurrence');

        return $recurrence instanceof Recurrence
            && $this->user()?->can('update', $recurrence);
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
