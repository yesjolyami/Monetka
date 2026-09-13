<?php

namespace App\Http\Requests\Transactions;

use App\Enums\CategoryKind;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            && $this->user()?->can('update', $transaction);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        $transaction = $this->route('transaction');
        $workspaceId = $transaction instanceof Transaction ? $transaction->workspace_id : 0;

        $rules = [
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId)],
            'amount' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        if (! $transaction instanceof Transaction) {
            return $rules;
        }

        return match ($transaction->type) {
            TransactionType::Transfer => $rules + [
                'counterparty_account_id' => [
                    'required',
                    'integer',
                    'different:account_id',
                    Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId),
                ],
            ],
            TransactionType::Income => $rules + $this->categoryRules($workspaceId, CategoryKind::Income),
            TransactionType::Expense => $rules + $this->categoryRules($workspaceId, CategoryKind::Expense),
            default => $rules,
        };
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

        $description = $this->input('description');

        $this->merge([
            'amount' => $amount,
            'description' => $description === '' ? null : $description,
        ]);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    private function categoryRules(int $workspaceId, CategoryKind $kind): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('workspace_id', $workspaceId)
                    ->where('kind', $kind->value),
            ],
        ];
    }
}
