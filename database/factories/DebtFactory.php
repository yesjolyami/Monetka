<?php

namespace Database\Factories;

use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'direction' => DebtDirection::TheyOwe,
            'counterparty_name' => fake()->name(),
            'original_amount' => 3000,
            'notes' => null,
        ];
    }
}
