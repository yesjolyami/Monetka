<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'kind' => CategoryKind::Expense,
            'name' => fake()->words(2, true),
            'emoji' => '📦',
            'color' => '#1C6CFF',
        ];
    }
}
