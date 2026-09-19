<?php

namespace App\Http\Requests\Receipts;

use App\Enums\CategoryKind;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportReceiptRequest extends FormRequest
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
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('workspace_id', $workspaceId)],
            'occurred_on' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'items.*.category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('workspace_id', $workspaceId)
                    ->where('kind', CategoryKind::Expense->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Выберите счёт, с которого списать покупки.',
            'items.required' => 'Выберите хотя бы одну позицию чека.',
            'items.min' => 'Выберите хотя бы одну позицию чека.',
            'items.*.amount.min' => 'Сумма позиции должна быть больше нуля.',
        ];
    }
}
