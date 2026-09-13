<?php

namespace App\Http\Requests\Goals;

use App\Models\Goal;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MoveGoalMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof Goal
            && $this->user()?->can('update', $goal);
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
            'amount' => ['required', 'integer', 'min:1'],
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

        $this->merge([
            'amount' => $amount,
        ]);
    }
}
