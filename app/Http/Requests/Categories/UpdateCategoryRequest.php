<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryKind;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            && $this->user()?->can('update', $category);
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
