<?php

namespace App\Actions\Import;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Category;
use App\Models\CategoryLimit;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportWorkspaceBackup
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $actor, array $payload, ?Workspace $replace): Workspace
    {
        $this->assertSchema($payload);

        if ($replace !== null) {
            Gate::forUser($actor)->authorize('replaceBackup', $replace);
        }

        return DB::transaction(function () use ($actor, $payload, $replace) {
            $workspace = $replace === null
                ? $this->createWorkspace($actor, $payload)
                : $this->wipeAndRename($replace, $payload);

            $this->insertFromPayload($actor, $workspace, $payload);

            return $workspace->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertSchema(array $payload): void
    {
        if ((int) ($payload['schema_version'] ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'file' => 'Неизвестная версия бэкапа.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createWorkspace(User $actor, array $payload): Workspace
    {
        $meta = is_array($payload['workspace'] ?? null) ? $payload['workspace'] : [];
        $name = $this->uniqueName($actor, is_string($meta['name'] ?? null) ? $meta['name'] : 'Бюджет');
        $currency = is_string($meta['currency'] ?? null) ? $meta['currency'] : 'RUB';

        return (new CreateWorkspace)->execute($actor, $name, $currency, false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function wipeAndRename(Workspace $workspace, array $payload): Workspace
    {
        $workspace->transactions()->delete();
        $workspace->recurrences()->delete();
        $workspace->categoryLimits()->delete();
        $workspace->goals()->delete();
        $workspace->debts()->delete();
        $workspace->accounts()->delete();
        $workspace->banks()->delete();
        $workspace->categories()->delete();
        $workspace->invitations()->delete();

        $meta = is_array($payload['workspace'] ?? null) ? $payload['workspace'] : [];

        $workspace->update([
            'name' => is_string($meta['name'] ?? null) ? $meta['name'] : $workspace->name,
            'currency' => is_string($meta['currency'] ?? null) ? $meta['currency'] : $workspace->currency,
        ]);

        return $workspace->refresh();
    }

    private function uniqueName(User $actor, string $name): string
    {
        $existing = $actor->workspaces()->pluck('workspaces.name');

        while ($existing->contains($name)) {
            $name .= ' (копия)';
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertFromPayload(User $actor, Workspace $workspace, array $payload): void
    {
        $bankMap = $this->insertBanks($workspace, $this->rows($payload, 'banks'));
        $categoryMap = $this->insertCategories($workspace, $this->rows($payload, 'categories'));
        $accountMap = $this->insertAccounts($workspace, $this->rows($payload, 'accounts'), $bankMap);
        $goalMap = $this->insertGoals($workspace, $this->rows($payload, 'goals'));
        $debtMap = $this->insertDebts($workspace, $this->rows($payload, 'debts'));
        $recurrenceMap = $this->insertRecurrences($workspace, $this->rows($payload, 'recurrences'), $accountMap, $categoryMap);
        $this->insertLimits($workspace, $this->rows($payload, 'limits'), $categoryMap);
        $this->insertTransactions($actor, $workspace, $this->rows($payload, 'transactions'), $accountMap, $categoryMap, $goalMap, $debtMap, $recurrenceMap);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function rows(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, is_array(...)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function insertBanks(Workspace $workspace, array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $bank = Bank::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $row['name'] ?? '',
                'color' => $row['color'] ?? null,
            ]);

            $map[(int) $row['id']] = $bank->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function insertCategories(Workspace $workspace, array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $category = Category::query()->create([
                'workspace_id' => $workspace->id,
                'kind' => $row['kind'],
                'name' => $row['name'] ?? '',
                'emoji' => $row['emoji'] ?? '',
                'color' => $row['color'] ?? '',
            ]);

            $map[(int) $row['id']] = $category->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, int>  $bankMap
     * @return array<int, int>
     */
    private function insertAccounts(Workspace $workspace, array $rows, array $bankMap): array
    {
        $map = [];

        foreach ($rows as $row) {
            $account = Account::query()->create([
                'workspace_id' => $workspace->id,
                'bank_id' => $this->mapped($row['bank_id'] ?? null, $bankMap),
                'user_id' => $this->resolveOwnerId($workspace, $row['owner_email'] ?? null),
                'name' => $row['name'] ?? '',
                'type' => $row['type'],
                'archived_at' => $row['archived_at'] ?? null,
            ]);

            $map[(int) $row['id']] = $account->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function insertGoals(Workspace $workspace, array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $goal = Goal::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $row['name'] ?? '',
                'target_amount' => $row['target_amount'] ?? 0,
                'target_date' => $row['target_date'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);

            $map[(int) $row['id']] = $goal->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function insertDebts(Workspace $workspace, array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $debt = Debt::query()->create([
                'workspace_id' => $workspace->id,
                'direction' => $row['direction'],
                'counterparty_name' => $row['counterparty_name'] ?? '',
                'original_amount' => $row['original_amount'] ?? 0,
                'notes' => $row['notes'] ?? null,
            ]);

            $map[(int) $row['id']] = $debt->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, int>  $accountMap
     * @param  array<int, int>  $categoryMap
     * @return array<int, int>
     */
    private function insertRecurrences(Workspace $workspace, array $rows, array $accountMap, array $categoryMap): array
    {
        $map = [];

        foreach ($rows as $row) {
            $recurrence = Recurrence::query()->create([
                'workspace_id' => $workspace->id,
                'type' => $row['type'],
                'account_id' => $this->mapped($row['account_id'] ?? null, $accountMap),
                'counterparty_account_id' => $this->mapped($row['counterparty_account_id'] ?? null, $accountMap),
                'category_id' => $this->mapped($row['category_id'] ?? null, $categoryMap),
                'amount' => $row['amount'] ?? 0,
                'description' => $row['description'] ?? null,
                'frequency' => $row['frequency'],
                'next_occurred_on' => $row['next_occurred_on'],
                'is_active' => $row['is_active'] ?? true,
            ]);

            $map[(int) $row['id']] = $recurrence->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, int>  $categoryMap
     */
    private function insertLimits(Workspace $workspace, array $rows, array $categoryMap): void
    {
        foreach ($rows as $row) {
            CategoryLimit::query()->create([
                'workspace_id' => $workspace->id,
                'category_id' => $this->mapped($row['category_id'] ?? null, $categoryMap),
                'amount' => $row['amount'] ?? 0,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, int>  $accountMap
     * @param  array<int, int>  $categoryMap
     * @param  array<int, int>  $goalMap
     * @param  array<int, int>  $debtMap
     * @param  array<int, int>  $recurrenceMap
     */
    private function insertTransactions(
        User $actor,
        Workspace $workspace,
        array $rows,
        array $accountMap,
        array $categoryMap,
        array $goalMap,
        array $debtMap,
        array $recurrenceMap,
    ): void {
        foreach ($rows as $row) {
            Transaction::query()->create([
                'workspace_id' => $workspace->id,
                'type' => $row['type'],
                'amount' => $row['amount'] ?? 0,
                'occurred_on' => $row['occurred_on'],
                'account_id' => $this->mapped($row['account_id'] ?? null, $accountMap),
                'counterparty_account_id' => $this->mapped($row['counterparty_account_id'] ?? null, $accountMap),
                'category_id' => $this->mapped($row['category_id'] ?? null, $categoryMap),
                'goal_id' => $this->mapped($row['goal_id'] ?? null, $goalMap),
                'debt_id' => $this->mapped($row['debt_id'] ?? null, $debtMap),
                'recurrence_id' => $this->mapped($row['recurrence_id'] ?? null, $recurrenceMap),
                'description' => $row['description'] ?? null,
                'user_id' => $actor->id,
            ]);
        }
    }

    private function resolveOwnerId(Workspace $workspace, mixed $email): ?int
    {
        if (! is_string($email) || $email === '') {
            return null;
        }

        $normalized = Str::lower($email);

        return $workspace->memberships()
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalized]))
            ->value('user_id');
    }

    /**
     * @param  array<int, int>  $map
     */
    private function mapped(mixed $oldId, array $map): ?int
    {
        if ($oldId === null || $oldId === '') {
            return null;
        }

        return $map[(int) $oldId] ?? null;
    }
}
