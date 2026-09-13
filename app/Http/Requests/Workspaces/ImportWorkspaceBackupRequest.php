<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportWorkspaceBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('view', $workspace);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:json', 'extensions:json', 'max:10240'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите JSON-файл.',
            'file.mimes' => 'Некорректный JSON.',
            'file.extensions' => 'Некорректный JSON.',
            'file.max' => 'Файл слишком большой.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('replace')) {
            $this->merge([
                'replace' => $this->boolean('replace'),
            ]);
        }
    }
}
