<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
        $workspace = $this->attributes->get('workspace');
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : 0;

        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'bank_id' => ['nullable', 'integer', Rule::exists('banks', 'id')->where('workspace_id', $workspaceId)],
            'user_id' => ['nullable', 'integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_id' => $this->input('bank_id') ?: null,
            'user_id' => $this->input('user_id') ?: null,
        ]);
    }
}
