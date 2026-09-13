<?php

namespace App\Actions\Export;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Category;
use App\Models\CategoryLimit;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\Workspace;
use BackedEnum;

class ExportWorkspaceBackup
{
    /**
     * @return array{
     *     schema_version: int,
     *     exported_at: string,
     *     workspace: array{name: string, currency: string},
     *     banks: list<array<string, mixed>>,
     *     accounts: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     transactions: list<array<string, mixed>>,
     *     goals: list<array<string, mixed>>,
     *     debts: list<array<string, mixed>>,
     *     limits: list<array<string, mixed>>,
     *     recurrences: list<array<string, mixed>>
     * }
     */
    public function execute(Workspace $workspace): array
    {
        $workspace->load([
            'banks',
            'accounts.user',
            'categories',
            'transactions',
            'goals',
            'debts',
            'categoryLimits',
            'recurrences',
        ]);

        return [
            'schema_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'workspace' => [
                'name' => $workspace->name,
                'currency' => $workspace->currency,
            ],
            'banks' => $workspace->banks->map(fn (Bank $bank): array => [
                'id' => $bank->id,
                'name' => $bank->name,
                'color' => $bank->color,
            ])->values()->all(),
            'accounts' => $workspace->accounts->map(fn (Account $account): array => [
                'id' => $account->id,
                'bank_id' => $account->bank_id,
                'name' => $account->name,
                'type' => $this->enumValue($account->type),
                'archived_at' => $account->archived_at?->toIso8601String(),
                'owner_email' => $account->user?->email,
            ])->values()->all(),
            'categories' => $workspace->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'kind' => $this->enumValue($category->kind),
                'name' => $category->name,
                'emoji' => $category->emoji,
                'color' => $category->color,
            ])->values()->all(),
            'transactions' => $workspace->transactions->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $this->enumValue($transaction->type),
                'amount' => $transaction->amount,
                'occurred_on' => $transaction->occurred_on->toDateString(),
                'account_id' => $transaction->account_id,
                'counterparty_account_id' => $transaction->counterparty_account_id,
                'category_id' => $transaction->category_id,
                'goal_id' => $transaction->goal_id,
                'debt_id' => $transaction->debt_id,
                'recurrence_id' => $transaction->recurrence_id,
                'description' => $transaction->description,
            ])->values()->all(),
            'goals' => $workspace->goals->map(fn (Goal $goal): array => [
                'id' => $goal->id,
                'name' => $goal->name,
                'target_amount' => $goal->target_amount,
                'target_date' => $goal->target_date?->toDateString(),
                'notes' => $goal->notes,
            ])->values()->all(),
            'debts' => $workspace->debts->map(fn (Debt $debt): array => [
                'id' => $debt->id,
                'direction' => $this->enumValue($debt->direction),
                'counterparty_name' => $debt->counterparty_name,
                'original_amount' => $debt->original_amount,
                'notes' => $debt->notes,
            ])->values()->all(),
            'limits' => $workspace->categoryLimits->map(fn (CategoryLimit $limit): array => [
                'id' => $limit->id,
                'category_id' => $limit->category_id,
                'amount' => $limit->amount,
            ])->values()->all(),
            'recurrences' => $workspace->recurrences->map(fn (Recurrence $recurrence): array => [
                'id' => $recurrence->id,
                'type' => $this->enumValue($recurrence->type),
                'account_id' => $recurrence->account_id,
                'counterparty_account_id' => $recurrence->counterparty_account_id,
                'category_id' => $recurrence->category_id,
                'amount' => $recurrence->amount,
                'description' => $recurrence->description,
                'frequency' => $this->enumValue($recurrence->frequency),
                'next_occurred_on' => $recurrence->next_occurred_on->toDateString(),
                'is_active' => $recurrence->is_active,
            ])->values()->all(),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
