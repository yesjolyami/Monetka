<?php

namespace App\Actions\Debts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepayDebt
{
    public function execute(Debt $debt, User $user, int $amount, ?Account $account, string $date, string $description): Transaction
    {
        return DB::transaction(function () use ($debt, $user, $account, $amount, $date, $description) {
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Сумма должна быть больше нуля.',
                ]);
            }

            if ($account !== null) {
                if ($account->workspace_id !== $debt->workspace_id) {
                    throw ValidationException::withMessages([
                        'account_id' => 'Счёт не найден в этом бюджете.',
                    ]);
                }

                $account->assertActive();
            }

            $locked = Debt::query()->whereKey($debt->id)->lockForUpdate()->firstOrFail();

            if ($amount > $locked->remainder()) {
                throw ValidationException::withMessages([
                    'amount' => 'Нельзя вернуть больше остатка долга.',
                ]);
            }

            return Transaction::query()->create([
                'workspace_id' => $locked->workspace_id,
                'type' => TransactionType::DebtRepayment,
                'amount' => $amount,
                'occurred_on' => $date,
                'account_id' => $account?->id,
                'debt_id' => $locked->id,
                'description' => $description === '' ? null : $description,
                'user_id' => $user->id,
            ]);
        });
    }
}
