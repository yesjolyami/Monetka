<?php

namespace App\Support;

use App\Enums\DebtDirection;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;

final class AccountBalance
{
    public static function for(Account $account): int
    {
        $accountId = $account->id;

        $sum = Transaction::query()
            ->leftJoin('debts', 'debts.id', '=', 'transactions.debt_id')
            ->where('transactions.workspace_id', $account->workspace_id)
            ->where(function ($query) use ($accountId): void {
                $query->where('transactions.account_id', $accountId)
                    ->orWhere('transactions.counterparty_account_id', $accountId);
            })
            ->selectRaw(
                'SUM(CASE
                    WHEN transactions.type = ? AND transactions.account_id = ? THEN transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? THEN -transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? THEN -transactions.amount
                    WHEN transactions.type = ? AND transactions.counterparty_account_id = ? THEN transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? THEN transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? THEN -transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? THEN transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? AND debts.direction = ? THEN transactions.amount
                    WHEN transactions.type = ? AND transactions.account_id = ? AND debts.direction = ? THEN -transactions.amount
                    WHEN transactions.type = ? THEN 0
                    ELSE 0
                END) as balance',
                [
                    TransactionType::Income->value,
                    $accountId,
                    TransactionType::Expense->value,
                    $accountId,
                    TransactionType::Transfer->value,
                    $accountId,
                    TransactionType::Transfer->value,
                    $accountId,
                    TransactionType::Adjustment->value,
                    $accountId,
                    TransactionType::GoalContribution->value,
                    $accountId,
                    TransactionType::GoalWithdrawal->value,
                    $accountId,
                    TransactionType::DebtRepayment->value,
                    $accountId,
                    DebtDirection::TheyOwe->value,
                    TransactionType::DebtRepayment->value,
                    $accountId,
                    DebtDirection::IOwe->value,
                    TransactionType::DebtRepayment->value,
                ],
            )
            ->value('balance');

        return (int) ($sum ?? 0);
    }
}
