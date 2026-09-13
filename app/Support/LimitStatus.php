<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\CategoryLimit;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

final class LimitStatus
{
    /**
     * @return array{spent: int, amount: int, exceeded: bool}
     */
    public static function for(CategoryLimit $limit, int $year, int $month): array
    {
        $from = Carbon::createFromDate($year, $month, 1)->toDateString();
        $to = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $spent = (int) Transaction::query()
            ->where('workspace_id', $limit->workspace_id)
            ->where('category_id', $limit->category_id)
            ->where('type', TransactionType::Expense)
            ->whereBetween('occurred_on', [$from, $to])
            ->sum('amount');

        $amount = (int) $limit->amount;

        return [
            'spent' => $spent,
            'amount' => $amount,
            'exceeded' => $spent > $amount,
        ];
    }
}
