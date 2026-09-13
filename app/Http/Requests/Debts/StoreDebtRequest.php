<?php

namespace App\Http\Requests\Debts;

use App\Enums\DebtDirection;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreDebtRequest extends FormRequest
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
            'direction' => ['required', 'string', Rule::enum(DebtDirection::class)],
            'counterparty_name' => ['required', 'string', 'max:80'],
            'original_amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'original_amount.integer' => 'Некорректная сумма.',
            'original_amount.required' => 'Укажите сумму.',
            'original_amount.min' => 'Сумма должна быть больше нуля.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('original_amount');

        if (is_string($amount) && $amount !== '') {
            try {
                $amount = Money::toMinor($amount);
            } catch (InvalidArgumentException) {
                // Leave the raw value so the integer rule fails with a Russian message.
            }
        }

        $notes = $this->input('notes');

        $this->merge([
            'original_amount' => $amount,
            'notes' => $notes === '' ? null : $notes,
        ]);
    }
}
