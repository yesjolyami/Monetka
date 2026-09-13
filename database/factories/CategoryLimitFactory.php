<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\CategoryLimit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryLimit>
 */
class CategoryLimitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'category_id' => fn (array $attributes): int => Category::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
                'kind' => CategoryKind::Expense,
            ])->id,
            'amount' => 10000,
        ];
    }
}
