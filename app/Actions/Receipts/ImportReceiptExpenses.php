<?php

namespace App\Actions\Receipts;

use App\Actions\Transactions\RecordExpense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportReceiptExpenses
{
    public function __construct(private RecordExpense $recordExpense) {}

    /**
     * @param  array{
     *     account_id: int,
     *     occurred_on: string,
     *     items: list<array{name: string, amount: int, category_id: int}>
     * }  $data
     */
    public function execute(Workspace $workspace, User $actor, array $data): int
    {
        $items = $data['items'];

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Выберите хотя бы одну позицию чека.',
            ]);
        }

        return DB::transaction(function () use ($workspace, $actor, $data, $items) {
            foreach ($items as $item) {
                $this->recordExpense->execute($workspace, $actor, [
                    'account_id' => $data['account_id'],
                    'category_id' => $item['category_id'],
                    'amount' => $item['amount'],
                    'occurred_on' => $data['occurred_on'],
                    'description' => $item['name'],
                ]);
            }

            return count($items);
        });
    }
}
