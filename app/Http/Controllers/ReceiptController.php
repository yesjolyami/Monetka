<?php

namespace App\Http\Controllers;

use App\Actions\Receipts\ConnectFnsAccount;
use App\Actions\Receipts\ImportReceiptExpenses;
use App\Actions\Receipts\LookupFnsReceipt;
use App\Actions\Receipts\VerifyFnsAccount;
use App\Enums\CategoryKind;
use App\Http\Requests\Receipts\ConnectFnsAccountRequest;
use App\Http\Requests\Receipts\ImportReceiptRequest;
use App\Http\Requests\Receipts\LookupReceiptRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SuggestReceiptCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        $categories = $workspace->categories()
            ->where('kind', CategoryKind::Expense)
            ->orderBy('id')
            ->get();

        $user = $request->user();

        return Inertia::render('receipts/Index', [
            'receipt' => $this->decorateReceipt($request->session()->get('receipt'), $categories),
            'connected' => $user instanceof User && $user->hasFnsCredentials(),
            'fnsInn' => $user instanceof User ? $user->fns_inn : null,
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
            'expenseCategories' => $categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'emoji' => $category->emoji,
                    'color' => $category->color,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function connect(
        ConnectFnsAccountRequest $request,
        ConnectFnsAccount $connectFnsAccount,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $data = $request->validated();
        $connectFnsAccount->execute($user, (string) $data['inn'], (string) $data['password']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Кабинет ФНС подключён.',
        ]);

        return back();
    }

    public function status(Request $request, VerifyFnsAccount $verifyFnsAccount): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $connected = $user->hasFnsCredentials();

        return response()->json([
            'connected' => $connected,
            'ok' => $connected && $verifyFnsAccount->execute($user),
        ]);
    }

    public function lookup(
        LookupReceiptRequest $request,
        LookupFnsReceipt $lookupFnsReceipt,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $data = $request->validated();

        $receipt = $lookupFnsReceipt->execute(
            $user,
            isset($data['qr']) && is_string($data['qr']) ? $data['qr'] : null,
            $request->file('photo'),
        );

        $request->session()->put('receipt', $receipt);

        return back();
    }

    public function import(
        ImportReceiptRequest $request,
        ImportReceiptExpenses $importReceiptExpenses,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $data = $request->validated();
        /** @var list<array{name: string, amount: int, category_id: int}> $items */
        $items = $data['items'];

        $count = $importReceiptExpenses->execute($this->currentWorkspace($request), $user, [
            'account_id' => (int) $data['account_id'],
            'occurred_on' => $data['occurred_on'],
            'items' => array_map(fn (array $item): array => [
                'name' => $item['name'],
                'amount' => (int) $item['amount'],
                'category_id' => (int) $item['category_id'],
            ], $items),
        ]);

        $request->session()->forget('receipt');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 1
                ? 'В журнал записан 1 расход.'
                : "В журнал записано {$count} расходов.",
        ]);

        return redirect()->route('transactions.index');
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<string, mixed>|null
     */
    private function decorateReceipt(mixed $receipt, Collection $categories): ?array
    {
        if (! is_array($receipt)) {
            return null;
        }

        $fallback = $categories->firstWhere('name', 'Продукты') ?? $categories->first();
        $items = $receipt['items'] ?? [];
        $decorated = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = is_string($item['name'] ?? null) ? $item['name'] : 'Позиция';
                $suggested = $categories->firstWhere('name', SuggestReceiptCategory::nameFor($name)) ?? $fallback;

                $decorated[] = [
                    ...$item,
                    'name' => $name,
                    'suggested_category_id' => $suggested?->id,
                ];
            }
        }

        $datetime = is_string($receipt['datetime'] ?? null) ? $receipt['datetime'] : null;

        return [
            ...$receipt,
            'occurred_on' => $this->occurredOn($datetime),
            'items' => $decorated,
        ];
    }

    private function occurredOn(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($datetime)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function currentWorkspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        return $workspace;
    }
}
