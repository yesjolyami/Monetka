<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case GoalContribution = 'goal_contribution';
    case GoalWithdrawal = 'goal_withdrawal';
    case DebtRepayment = 'debt_repayment';

    public function isLedgerLocked(): bool
    {
        return match ($this) {
            self::Adjustment,
            self::GoalContribution,
            self::GoalWithdrawal,
            self::DebtRepayment => true,
            default => false,
        };
    }
}
