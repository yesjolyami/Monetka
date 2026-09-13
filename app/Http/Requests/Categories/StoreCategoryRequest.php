<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryKind;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        return [
            'kind' => ['required', 'string', Rule::enum(CategoryKind::class)],
            'name' => ['required', 'string', 'max:80'],
            'emoji' => ['required', 'string', 'max:16'],
            'color' => ['required', 'string', 'max:20'],
        ];
    }
}
