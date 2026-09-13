<?php

namespace App\Actions\Recurrences\Concerns;

use App\Actions\Transactions\Concerns\ResolvesLedgerTargets;
use App\Enums\CategoryKind;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceType;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

trait PreparesRecurrenceData
{
    use ResolvesLedgerTargets;

    /**
     * @param  array{type: string, account_id: int, counterparty_account_id: int|null, category_id: int|null, amount: int, description?: string|null, frequency: string, next_occurred_on: string}  $data
     * @return array{type: RecurrenceType, account_id: int, counterparty_account_id: int|null, category_id: int|null, amount: int, description: string|null, frequency: RecurrenceFrequency, next_occurred_on: string}
     */
    protected function prepared(Workspace $workspace, array $data): array
    {
        $type = RecurrenceType::tryFrom((string) $data['type']);

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => 'Некорректный тип шаблона.',
            ]);
        }

        $frequency = RecurrenceFrequency::tryFrom((string) $data['frequency']);

        if ($frequency === null) {
            throw ValidationException::withMessages([
                'frequency' => 'Некорректная периодичность.',
            ]);
        }

        $fields = $this->validatedAmountAndDescription($data);
        $account = $this->resolveAccount($workspace, (int) $data['account_id']);

        if ($type === RecurrenceType::Transfer) {
            $counterpartyId = $data['counterparty_account_id'] ?? null;

            if ($counterpartyId === null) {
                throw ValidationException::withMessages([
                    'counterparty_account_id' => 'Укажите счёт зачисления.',
                ]);
            }

            $this->resolveTransferAccounts($workspace, $account->id, (int) $counterpartyId);

            return [
                'type' => $type,
                'account_id' => $account->id,
                'counterparty_account_id' => (int) $counterpartyId,
                'category_id' => null,
                'amount' => $fields['amount'],
                'description' => $fields['description'],
                'frequency' => $frequency,
                'next_occurred_on' => $data['next_occurred_on'],
            ];
        }

        $kind = $type === RecurrenceType::Income ? CategoryKind::Income : CategoryKind::Expense;
        $categoryId = $data['category_id'] ?? null;

        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'category_id' => 'Укажите категорию.',
            ]);
        }

        $category = $this->resolveCategory($workspace, (int) $categoryId, $kind);

        return [
            'type' => $type,
            'account_id' => $account->id,
            'counterparty_account_id' => null,
            'category_id' => $category->id,
            'amount' => $fields['amount'],
            'description' => $fields['description'],
            'frequency' => $frequency,
            'next_occurred_on' => $data['next_occurred_on'],
        ];
    }
}
