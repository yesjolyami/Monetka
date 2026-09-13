<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\ResolvesLedgerTargets;
use App\Enums\CategoryKind;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTransaction
{
    use ResolvesLedgerTargets;

    /**
     * @param  array{account_id: int, category_id?: int, counterparty_account_id?: int, amount: int, occurred_on: string, description?: string|null}  $data
     */
    public function execute(Transaction $transaction, User $actor, array $data): Transaction
    {
        if ($transaction->type->isLedgerLocked()) {
            throw ValidationException::withMessages([
                'transaction' => 'Эту операцию нельзя менять. Для баланса счёта используйте «Выставить баланс».',
            ]);
        }

        return DB::transaction(function () use ($transaction, $data) {
            $workspace = Workspace::query()->find($transaction->workspace_id);

            if ($workspace === null) {
                throw ValidationException::withMessages([
                    'account_id' => 'Счёт не найден в этом бюджете.',
                ]);
            }

            $fields = $this->validatedAmountAndDescription($data);
            $attributes = $this->attributesFor($transaction->type, $workspace, $data, $fields);

            $transaction->update($attributes);

            return $transaction;
        });
    }

    /**
     * @param  array{account_id: int, category_id?: int, counterparty_account_id?: int, amount: int, occurred_on: string, description?: string|null}  $data
     * @param  array{amount: int, description: string|null}  $fields
     * @return array<string, mixed>
     */
    private function attributesFor(TransactionType $type, Workspace $workspace, array $data, array $fields): array
    {
        $base = [
            'amount' => $fields['amount'],
            'occurred_on' => $data['occurred_on'],
            'description' => $fields['description'],
        ];

        return match ($type) {
            TransactionType::Transfer => $base + $this->transferTargets($workspace, $data),
            TransactionType::Income => $base + $this->categorizedTargets($workspace, $data, CategoryKind::Income),
            TransactionType::Expense => $base + $this->categorizedTargets($workspace, $data, CategoryKind::Expense),
            default => $base + $this->accountTarget($workspace, $data),
        };
    }

    /**
     * @param  array{account_id: int, counterparty_account_id?: int}  $data
     * @return array{account_id: int, counterparty_account_id: int, category_id: null}
     */
    private function transferTargets(Workspace $workspace, array $data): array
    {
        if (! isset($data['counterparty_account_id'])) {
            throw ValidationException::withMessages([
                'counterparty_account_id' => 'Счёт не найден в этом бюджете.',
            ]);
        }

        [$from, $to] = $this->resolveTransferAccounts(
            $workspace,
            (int) $data['account_id'],
            (int) $data['counterparty_account_id'],
            mustBeActive: false,
        );

        return [
            'account_id' => $from->id,
            'counterparty_account_id' => $to->id,
            'category_id' => null,
        ];
    }

    /**
     * @param  array{account_id: int, category_id?: int}  $data
     * @return array{account_id: int, counterparty_account_id: null, category_id: int}
     */
    private function categorizedTargets(Workspace $workspace, array $data, CategoryKind $kind): array
    {
        if (! isset($data['category_id'])) {
            throw ValidationException::withMessages([
                'category_id' => 'Категория не найдена в этом бюджете.',
            ]);
        }

        $account = $this->resolveAccount($workspace, (int) $data['account_id'], 'account_id', false);
        $category = $this->resolveCategory($workspace, (int) $data['category_id'], $kind);

        return [
            'account_id' => $account->id,
            'counterparty_account_id' => null,
            'category_id' => $category->id,
        ];
    }

    /**
     * @param  array{account_id: int}  $data
     * @return array{account_id: int}
     */
    private function accountTarget(Workspace $workspace, array $data): array
    {
        $account = $this->resolveAccount($workspace, (int) $data['account_id'], 'account_id', false);

        return [
            'account_id' => $account->id,
        ];
    }
}
