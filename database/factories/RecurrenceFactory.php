<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurrence>
 */
class RecurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => RecurrenceType::Expense,
            'account_id' => fn (array $attributes): int => Account::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
            ])->id,
            'counterparty_account_id' => null,
            'category_id' => fn (array $attributes): int => Category::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
                'kind' => CategoryKind::Expense,
            ])->id,
            'amount' => 500,
            'description' => 'Подписка',
            'frequency' => RecurrenceFrequency::Monthly,
            'next_occurred_on' => '2026-09-01',
            'is_active' => true,
        ];
    }
}
