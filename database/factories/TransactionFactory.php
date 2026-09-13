<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => TransactionType::Income,
            'amount' => 100,
            'occurred_on' => now()->toDateString(),
            'account_id' => Account::factory(),
            'counterparty_account_id' => null,
            'category_id' => null,
            'goal_id' => null,
            'debt_id' => null,
            'recurrence_id' => null,
            'description' => null,
            'user_id' => null,
        ];
    }
}
