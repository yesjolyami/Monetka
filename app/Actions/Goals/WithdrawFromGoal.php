<?php

namespace App\Actions\Goals;

use App\Actions\Goals\Concerns\MovesGoalMoney;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawFromGoal
{
    use MovesGoalMoney;

    public function execute(Goal $goal, User $user, Account $account, int $amount, string $date): Transaction
    {
        return DB::transaction(function () use ($goal, $user, $account, $amount, $date) {
            $this->assertPositiveAmount($amount);
            $this->assertAccountInWorkspace($goal, $account);
            $account->assertActive();

            $locked = Goal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();

            if ($amount > $locked->progress()) {
                throw ValidationException::withMessages([
                    'amount' => 'Нельзя снять больше, чем накоплено по цели.',
                ]);
            }

            return Transaction::query()->create([
                'workspace_id' => $locked->workspace_id,
                'type' => TransactionType::GoalWithdrawal,
                'amount' => $amount,
                'occurred_on' => $date,
                'account_id' => $account->id,
                'goal_id' => $locked->id,
                'user_id' => $user->id,
            ]);
        });
    }
}
