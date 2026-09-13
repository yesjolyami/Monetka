<?php

namespace App\Http\Requests\Debts;

use App\Models\Debt;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class RepayDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $debt = $this->route('debt');

        return $debt instanceof Debt
            && $this->user()?->can('update', $debt);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : 0;

        return [
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId)],
            'amount' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
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

        $accountId = $this->input('account_id');
        $description = $this->input('description');

        $this->merge([
            'amount' => $amount,
            'account_id' => $accountId === '' ? null : $accountId,
            'description' => $description === '' ? null : $description,
        ]);
    }
}
