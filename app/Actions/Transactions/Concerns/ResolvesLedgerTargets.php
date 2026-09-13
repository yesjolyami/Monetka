<?php

namespace App\Actions\Transactions\Concerns;

use App\Enums\CategoryKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

trait ResolvesLedgerTargets
{
    protected function resolveAccount(Workspace $workspace, int $accountId, string $field = 'account_id', bool $mustBeActive = true): Account
    {
        $account = Account::query()->find($accountId);

        if ($account === null || $account->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages([
                $field => 'Счёт не найден в этом бюджете.',
            ]);
        }

        if ($mustBeActive) {
            $account->assertActive();
        }

        return $account;
    }

    /**
     * @return array{0: Account, 1: Account}
     */
    protected function resolveTransferAccounts(Workspace $workspace, int $fromId, int $toId, bool $mustBeActive = true): array
    {
        $from = $this->resolveAccount($workspace, $fromId, 'account_id', $mustBeActive);
        $to = $this->resolveAccount($workspace, $toId, 'counterparty_account_id', $mustBeActive);

        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'counterparty_account_id' => 'Нельзя перевести на тот же счёт.',
            ]);
        }

        return [$from, $to];
    }

    protected function resolveCategory(Workspace $workspace, int $categoryId, CategoryKind $kind): Category
    {
        $category = Category::query()->find($categoryId);

        if ($category === null || $category->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages([
                'category_id' => 'Категория не найдена в этом бюджете.',
            ]);
        }

        if ($category->kind !== $kind) {
            throw ValidationException::withMessages([
                'category_id' => 'Категория не подходит для этой операции.',
            ]);
        }

        return $category;
    }

    /**
     * @param  array{amount: int, description?: string|null}  $data
     * @return array{amount: int, description: string|null}
     */
    protected function validatedAmountAndDescription(array $data): array
    {
        $amount = (int) $data['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма должна быть больше нуля.',
            ]);
        }

        $description = $data['description'] ?? null;

        if ($description === '') {
            $description = null;
        }

        return [
            'amount' => $amount,
            'description' => $description,
        ];
    }
}
