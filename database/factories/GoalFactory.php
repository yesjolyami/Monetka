<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'target_amount' => 50000,
            'target_date' => '2026-12-01',
            'notes' => null,
        ];
    }
}
