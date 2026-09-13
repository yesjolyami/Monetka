<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Concerns\ResolvesLedgerTargets;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class TransferBetweenAccounts
{
    use ResolvesLedgerTargets;

    /**
     * @param  array{account_id: int, counterparty_account_id: int, amount: int, occurred_on: string, description?: string|null}  $data
     */
    public function execute(Workspace $workspace, User $actor, array $data): Transaction
    {
        return DB::transaction(function () use ($workspace, $actor, $data) {
            [$from, $to] = $this->resolveTransferAccounts(
                $workspace,
                (int) $data['account_id'],
                (int) $data['counterparty_account_id'],
            );
            $fields = $this->validatedAmountAndDescription($data);

            return Transaction::query()->create([
                'workspace_id' => $workspace->id,
                'type' => TransactionType::Transfer,
                'amount' => $fields['amount'],
                'occurred_on' => $data['occurred_on'],
                'account_id' => $from->id,
                'counterparty_account_id' => $to->id,
                'category_id' => null,
                'description' => $fields['description'],
                'user_id' => $actor->id,
            ]);
        });
    }
}
