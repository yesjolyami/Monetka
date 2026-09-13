<?php

namespace App\Http\Requests\Transactions;

use App\Enums\CategoryKind;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

abstract class StoreMoneyMovementRequest extends FormRequest
{
    abstract protected function categoryKind(): CategoryKind;

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

        return [
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId)],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('workspace_id', $workspaceId)
                    ->where('kind', $this->categoryKind()->value),
            ],
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

        $description = $this->input('description');

        $this->merge([
            'amount' => $amount,
            'description' => $description === '' ? null : $description,
        ]);
    }
}
