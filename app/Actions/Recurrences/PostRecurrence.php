<?php

namespace App\Actions\Recurrences;

use App\Actions\Transactions\RecordExpense;
use App\Actions\Transactions\RecordIncome;
use App\Actions\Transactions\TransferBetweenAccounts;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceType;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostRecurrence
{
    public function execute(Recurrence $recurrence, User $actor): Transaction
    {
        return DB::transaction(function () use ($recurrence, $actor) {
            if (! $recurrence->is_active) {
                throw ValidationException::withMessages([
                    'recurrence' => 'Нельзя провести неактивный шаблон.',
                ]);
            }

            $workspace = Workspace::query()->findOrFail($recurrence->workspace_id);
            $occurredOn = $recurrence->next_occurred_on->toDateString();
            $description = $recurrence->description;

            $tx = match ($recurrence->type) {
                RecurrenceType::Income => (new RecordIncome)->execute($workspace, $actor, [
                    'account_id' => $recurrence->account_id,
                    'category_id' => (int) $recurrence->category_id,
                    'amount' => $recurrence->amount,
                    'occurred_on' => $occurredOn,
                    'description' => $description,
                ]),
                RecurrenceType::Expense => (new RecordExpense)->execute($workspace, $actor, [
                    'account_id' => $recurrence->account_id,
                    'category_id' => (int) $recurrence->category_id,
                    'amount' => $recurrence->amount,
                    'occurred_on' => $occurredOn,
                    'description' => $description,
                ]),
                RecurrenceType::Transfer => (new TransferBetweenAccounts)->execute($workspace, $actor, [
                    'account_id' => $recurrence->account_id,
                    'counterparty_account_id' => (int) $recurrence->counterparty_account_id,
                    'amount' => $recurrence->amount,
                    'occurred_on' => $occurredOn,
                    'description' => $description,
                ]),
            };

            $tx->forceFill(['recurrence_id' => $recurrence->id])->save();

            $next = Carbon::parse($occurredOn);
            $recurrence->next_occurred_on = match ($recurrence->frequency) {
                RecurrenceFrequency::Weekly => $next->addWeek(),
                RecurrenceFrequency::Monthly => $next->addMonthNoOverflow(),
                RecurrenceFrequency::Yearly => $next->addYear(),
            };
            $recurrence->save();

            return $tx;
        });
    }
}
