<?php

namespace App\Actions\Export;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTransactionsCsv
{
    public function execute(Workspace $workspace, string $month): StreamedResponse
    {
        $filename = $month === 'all'
            ? 'monetka-operations-all.csv'
            : "monetka-operations-{$month}.csv";

        return response()->streamDownload(function () use ($workspace, $month): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Дата',
                'Тип',
                'Сумма',
                'Валюта',
                'Счёт',
                'Счёт-получатель',
                'Категория',
                'Описание',
            ], ',', '"', '');

            $query = $workspace->transactions()
                ->with(['account', 'counterpartyAccount', 'category']);

            if ($month !== 'all') {
                $start = Carbon::parse($month.'-01')->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $query->whereDate('occurred_on', '>=', $start->toDateString())
                    ->whereDate('occurred_on', '<=', $end->toDateString());
            }

            foreach ($query->lazyById() as $transaction) {
                fputcsv($output, $this->row($workspace, $transaction), ',', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<string>
     */
    private function row(Workspace $workspace, Transaction $transaction): array
    {
        return [
            $transaction->occurred_on->toDateString(),
            $this->typeLabel($transaction->type),
            Money::toDecimal($transaction->amount),
            $workspace->currency,
            $transaction->account?->name ?? '',
            $transaction->counterpartyAccount?->name ?? '',
            $transaction->category?->name ?? '',
            $transaction->description ?? '',
        ];
    }

    private function typeLabel(TransactionType $type): string
    {
        return match ($type) {
            TransactionType::Income => 'Доход',
            TransactionType::Expense => 'Расход',
            TransactionType::Transfer => 'Перевод',
            TransactionType::Adjustment => 'корректировка',
            TransactionType::GoalContribution => 'Пополнение цели',
            TransactionType::GoalWithdrawal => 'Снятие с цели',
            TransactionType::DebtRepayment => 'Возврат долга',
        };
    }
}
