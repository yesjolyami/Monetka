<?php

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SetAccountBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account
            && $this->user()?->can('update', $account);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer'],
            'occurred_on' => ['required', 'date'],
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

        $this->merge([
            'amount' => $amount,
        ]);
    }
}
