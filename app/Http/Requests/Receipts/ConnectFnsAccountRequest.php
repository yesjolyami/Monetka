<?php

namespace App\Http\Requests\Receipts;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConnectFnsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('view', $workspace);
    }

    protected function prepareForValidation(): void
    {
        $inn = $this->input('inn');

        if (is_string($inn)) {
            $this->merge([
                'inn' => preg_replace('/\D+/', '', $inn) ?? '',
            ]);
        }
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'inn' => ['required', 'string', 'regex:/^\d{10}(\d{2})?$/'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inn.required' => 'Укажите ИНН из личного кабинета nalog.ru.',
            'inn.regex' => 'ИНН должен содержать 10 или 12 цифр.',
            'password.required' => 'Укажите пароль личного кабинета nalog.ru.',
        ];
    }
}
