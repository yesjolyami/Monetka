<?php

namespace App\Http\Requests\Limits;

use App\Models\CategoryLimit;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateCategoryLimitRequest extends StoreCategoryLimitRequest
{
    public function authorize(): bool
    {
        $limit = $this->route('categoryLimit');

        return $limit instanceof CategoryLimit
            && $this->user()?->can('update', $limit);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
