<?php

namespace App\Http\Controllers;

use App\Actions\Banks\CreateBank;
use App\Actions\Banks\DeleteBank;
use App\Actions\Banks\UpdateBank;
use App\Http\Requests\Banks\StoreBankRequest;
use App\Http\Requests\Banks\UpdateBankRequest;
use App\Models\Bank;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankController extends Controller
{
    public function store(StoreBankRequest $request, CreateBank $createBank): RedirectResponse
    {
        $data = $request->validated();

        $createBank->execute($this->currentWorkspace($request), [
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
        ]);

        return redirect()->route('accounts.index');
    }

    public function update(UpdateBankRequest $request, Bank $bank, UpdateBank $updateBank): RedirectResponse
    {
        Gate::authorize('update', $bank);
        $this->assertInWorkspace($request, $bank->workspace_id);

        $data = $request->validated();

        $updateBank->execute($bank, [
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
        ]);

        return back();
    }

    public function destroy(Request $request, Bank $bank, DeleteBank $deleteBank): RedirectResponse
    {
        Gate::authorize('delete', $bank);
        $this->assertInWorkspace($request, $bank->workspace_id);

        $deleteBank->execute($bank);

        return back();
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
