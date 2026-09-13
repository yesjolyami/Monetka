<?php

namespace App\Http\Controllers;

use App\Actions\Recurrences\CreateRecurrence;
use App\Actions\Recurrences\DeactivateRecurrence;
use App\Actions\Recurrences\PostRecurrence;
use App\Actions\Recurrences\UpdateRecurrence;
use App\Enums\CategoryKind;
use App\Http\Requests\Recurrences\DeactivateRecurrenceRequest;
use App\Http\Requests\Recurrences\PostRecurrenceRequest;
use App\Http\Requests\Recurrences\StoreRecurrenceRequest;
use App\Http\Requests\Recurrences\UpdateRecurrenceRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecurrenceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        $recurrences = $workspace->recurrences()
            ->with(['account', 'counterpartyAccount', 'category'])
            ->orderByDesc('is_active')
            ->orderBy('next_occurred_on')
            ->get();

        return Inertia::render('recurrences/Index', [
            'recurrences' => $this->payloads($recurrences),
            'accounts' => $workspace->accounts()
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                ])
                ->values()
                ->all(),
            'incomeCategories' => $this->categoryPayloads(
                $workspace->categories()->where('kind', CategoryKind::Income)->orderBy('name')->get(),
            ),
            'expenseCategories' => $this->categoryPayloads(
                $workspace->categories()->where('kind', CategoryKind::Expense)->orderBy('name')->get(),
            ),
        ]);
    }

    public function store(StoreRecurrenceRequest $request, CreateRecurrence $createRecurrence): RedirectResponse
    {
        $data = $request->validated();

        $createRecurrence->execute($this->currentWorkspace($request), [
            'type' => $data['type'],
            'account_id' => (int) $data['account_id'],
            'counterparty_account_id' => isset($data['counterparty_account_id']) ? (int) $data['counterparty_account_id'] : null,
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'amount' => (int) $data['amount'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'],
            'next_occurred_on' => $data['next_occurred_on'],
        ]);

        return redirect()->route('recurrences.index');
    }

    public function update(UpdateRecurrenceRequest $request, Recurrence $recurrence, UpdateRecurrence $updateRecurrence): RedirectResponse
    {
        Gate::authorize('update', $recurrence);
        $this->assertInWorkspace($request, $recurrence->workspace_id);

        $data = $request->validated();

        $updateRecurrence->execute($recurrence, [
            'type' => $data['type'],
            'account_id' => (int) $data['account_id'],
            'counterparty_account_id' => isset($data['counterparty_account_id']) ? (int) $data['counterparty_account_id'] : null,
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'amount' => (int) $data['amount'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'],
            'next_occurred_on' => $data['next_occurred_on'],
        ]);

        return back();
    }

    public function post(PostRecurrenceRequest $request, Recurrence $recurrence, PostRecurrence $postRecurrence): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        Gate::authorize('update', $recurrence);
        $this->assertInWorkspace($request, $recurrence->workspace_id);

        $postRecurrence->execute($recurrence, $user);

        return back();
    }

    public function deactivate(DeactivateRecurrenceRequest $request, Recurrence $recurrence, DeactivateRecurrence $deactivateRecurrence): RedirectResponse
    {
        Gate::authorize('update', $recurrence);
        $this->assertInWorkspace($request, $recurrence->workspace_id);

        $deactivateRecurrence->execute($recurrence);

        return back();
    }

    /**
     * @param  Collection<int, Recurrence>  $recurrences
     * @return array<int, array{id: int, type: string, account_id: int, counterparty_account_id: int|null, category_id: int|null, amount: int, description: string|null, frequency: string, next_occurred_on: string, is_active: bool, account_name: string, counterparty_account_name: string|null, category_name: string|null}>
     */
    private function payloads(Collection $recurrences): array
    {
        return $recurrences
            ->map(fn (Recurrence $recurrence): array => [
                'id' => $recurrence->id,
                'type' => $recurrence->type->value,
                'account_id' => $recurrence->account_id,
                'counterparty_account_id' => $recurrence->counterparty_account_id,
                'category_id' => $recurrence->category_id,
                'amount' => $recurrence->amount,
                'description' => $recurrence->description,
                'frequency' => $recurrence->frequency->value,
                'next_occurred_on' => $recurrence->next_occurred_on->toDateString(),
                'is_active' => $recurrence->is_active,
                'account_name' => $recurrence->account->name,
                'counterparty_account_name' => $recurrence->counterpartyAccount?->name,
                'category_name' => $recurrence->category?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, array{id: int, name: string, emoji: string}>
     */
    private function categoryPayloads(Collection $categories): array
    {
        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'emoji' => $category->emoji,
            ])
            ->values()
            ->all();
    }

    private function currentWorkspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        return $workspace;
    }

    private function assertInWorkspace(Request $request, int $workspaceId): Workspace
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($workspaceId === $workspace->id, 404);

        return $workspace;
    }
}
