<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\ResolvesLedgerTargets;
use App\Enums\CategoryKind;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class RecordExpense
{
    use ResolvesLedgerTargets;

    /**
     * @param  array{account_id: int, category_id: int, amount: int, occurred_on: string, description?: string|null}  $data
     */
    public function execute(Workspace $workspace, User $actor, array $data): Transaction
    {
        return DB::transaction(function () use ($workspace, $actor, $data) {
            $account = $this->resolveAccount($workspace, (int) $data['account_id']);
            $category = $this->resolveCategory($workspace, (int) $data['category_id'], CategoryKind::Expense);
            $fields = $this->validatedAmountAndDescription($data);

            return Transaction::query()->create([
                'workspace_id' => $workspace->id,
                'type' => TransactionType::Expense,
                'amount' => $fields['amount'],
                'occurred_on' => $data['occurred_on'],
                'account_id' => $account->id,
                'category_id' => $category->id,
                'description' => $fields['description'],
                'user_id' => $actor->id,
            ]);
        });
    }
}
