<?php

namespace App\Actions\Goals\Concerns;

use App\Models\Account;
use App\Models\Goal;
use Illuminate\Validation\ValidationException;

trait MovesGoalMoney
{
    protected function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма должна быть больше нуля.',
            ]);
        }
    }

    protected function assertAccountInWorkspace(Goal $goal, Account $account): void
    {
        if ($account->workspace_id !== $goal->workspace_id) {
            throw ValidationException::withMessages([
                'account_id' => 'Счёт не найден в этом бюджете.',
            ]);
        }
    }
}
