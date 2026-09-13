<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountType;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'bank_id' => ['nullable', 'integer', Rule::exists('banks', 'id')->where('workspace_id', $workspaceId)],
            'user_id' => ['nullable', 'integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
            'opening_balance' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_balance.integer' => 'Некорректная сумма.',
            'opening_balance.required' => 'Укажите стартовый баланс.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $opening = $this->input('opening_balance');

        if (is_string($opening) && $opening !== '') {
            try {
                $opening = Money::toMinor($opening);
            } catch (InvalidArgumentException) {
                // Leave the raw value so the integer rule fails with a Russian message.
            }
        } elseif ($opening === '' || $opening === null) {
            $opening = 0;
        }

        $this->merge([
            'opening_balance' => $opening,
            'bank_id' => $this->input('bank_id') ?: null,
            'user_id' => $this->input('user_id') ?: null,
        ]);
    }
}
