<?php

namespace App\Http\Requests\Limits;

use App\Enums\CategoryKind;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreCategoryLimitRequest extends FormRequest
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

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('workspace_id', $workspaceId)
                    ->where('kind', CategoryKind::Expense->value),
            ],
            'amount' => ['required', 'integer', 'min:1'],
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
            'category_id.exists' => 'Лимит можно задать только для категории расходов.',
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
