<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'currency' => ['required', 'string', 'in:RUB,USD,EUR'],
        ];
    }
}
