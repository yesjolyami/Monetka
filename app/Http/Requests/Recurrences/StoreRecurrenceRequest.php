<?php

namespace App\Http\Requests\Recurrences;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceType;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreRecurrenceRequest extends FormRequest
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
        $workspace = $this->attributes->get('workspace');
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : 0;
        $isTransfer = $this->input('type') === RecurrenceType::Transfer->value;
        $needsCategory = in_array($this->input('type'), [
            RecurrenceType::Income->value,
            RecurrenceType::Expense->value,
        ], true);

        return [
            'type' => ['required', 'string', Rule::enum(RecurrenceType::class)],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId),
            ],
            'counterparty_account_id' => [
                Rule::requiredIf($isTransfer),
                'nullable',
                'integer',
                'different:account_id',
                Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId),
            ],
            'category_id' => [
                Rule::requiredIf($needsCategory),
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('workspace_id', $workspaceId),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'string', Rule::enum(RecurrenceFrequency::class)],
            'next_occurred_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.integer' => 'Некорректная сумма.',
            'amount.required' => 'Укажите сумму.',
            'amount.min' => 'Сумма должна быть больше нуля.',
            'counterparty_account_id.different' => 'Нельзя перевести на тот же счёт.',
            'counterparty_account_id.required' => 'Укажите счёт зачисления.',
            'category_id.required' => 'Укажите категорию.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        if (is_string($amount) && $amount !== '') {
            try {
                $amount = Money::toMinor($amount);
            } catch (InvalidArgumentException) {
                // Leave the raw value so the integer rule fails with a Russian message.
            }
        }

        $counterparty = $this->input('counterparty_account_id');
        $category = $this->input('category_id');
        $description = $this->input('description');

        $this->merge([
            'amount' => $amount,
            'counterparty_account_id' => $counterparty === '' ? null : $counterparty,
            'category_id' => $category === '' ? null : $category,
            'description' => $description === '' ? null : $description,
        ]);
    }
}
