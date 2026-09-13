<?php

namespace App\Actions\Goals;

use App\Actions\Goals\Concerns\MovesGoalMoney;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContributeToGoal
{
    use MovesGoalMoney;

    public function execute(Goal $goal, User $user, Account $account, int $amount, string $date): Transaction
    {
        return DB::transaction(function () use ($goal, $user, $account, $amount, $date) {
            $this->assertPositiveAmount($amount);
            $this->assertAccountInWorkspace($goal, $account);
            $account->assertActive();

            return Transaction::query()->create([
                'workspace_id' => $goal->workspace_id,
                'type' => TransactionType::GoalContribution,
                'amount' => $amount,
                'occurred_on' => $date,
                'account_id' => $account->id,
                'goal_id' => $goal->id,
                'user_id' => $user->id,
            ]);
        });
    }
}
