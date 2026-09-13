<?php

namespace App\Http\Requests\Banks;

use App\Models\Bank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = $this->route('bank');

        return $bank instanceof Bank
            && $this->user()?->can('update', $bank);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => $this->input('color') ?: null,
        ]);
    }
}
