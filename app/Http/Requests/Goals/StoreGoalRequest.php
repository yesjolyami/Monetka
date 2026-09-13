<?php

namespace App\Http\Requests\Goals;

use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('update', $workspace);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'target_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_amount.integer' => 'Некорректная сумма.',
            'target_amount.required' => 'Укажите сумму.',
            'target_amount.min' => 'Сумма должна быть больше нуля.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $target = $this->input('target_amount');

        if (is_string($target) && $target !== '') {
            try {
                $target = Money::toMinor($target);
            } catch (InvalidArgumentException) {
                // Leave the raw value so the integer rule fails with a Russian message.
            }
        }

        $notes = $this->input('notes');
        $targetDate = $this->input('target_date');

        $this->merge([
            'target_amount' => $target,
            'target_date' => $targetDate === '' ? null : $targetDate,
            'notes' => $notes === '' ? null : $notes,
        ]);
    }
}
