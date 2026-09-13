<?php

namespace App\Http\Controllers;

use App\Actions\Import\ImportWorkspaceBackup;
use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\LeaveWorkspace;
use App\Actions\Workspaces\RemoveMember;
use App\Actions\Workspaces\SwitchWorkspace;
use App\Actions\Workspaces\TransferOwnership;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Requests\Workspaces\DeleteWorkspaceRequest;
use App\Http\Requests\Workspaces\ImportWorkspaceBackupRequest;
use App\Http\Requests\Workspaces\LeaveWorkspaceRequest;
use App\Http\Requests\Workspaces\RemoveMemberRequest;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\TransferOwnershipRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('workspaces/Create');
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $data = $request->validated();

        $createWorkspace->execute($request->user(), $data['name'], $data['currency']);

        return redirect()->route('dashboard');
    }

    public function settings(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        $user = $request->user();

        $members = $workspace->memberships()
            ->with('user')
            ->get()
            ->sortBy(fn (WorkspaceMembership $membership): array => [
                $membership->role->value === 'owner' ? 0 : 1,
                mb_strtolower($membership->user->name),
            ])
            ->values()
            ->map(fn (WorkspaceMembership $membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
            ])
            ->all();

        $invitations = $user->can('invite', $workspace)
            ? $workspace->invitations()
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->orderBy('email')
                ->get()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('workspaces/Settings', [
            'workspaceName' => $workspace->name,
            'currency' => $workspace->currency,
            'hasTransactions' => $workspace->transactions()->exists(),
            'members' => $members,
            'invitations' => $invitations,
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, UpdateWorkspace $updateWorkspace): RedirectResponse
    {
        $updateWorkspace->execute($request->user(), $this->currentWorkspace($request), $request->validated());

        return back();
    }

    public function transfer(TransferOwnershipRequest $request, TransferOwnership $transferOwnership): RedirectResponse
    {
        $data = $request->validated();
        $newOwner = User::query()->findOrFail($data['user_id']);

        $transferOwnership->execute($request->user(), $this->currentWorkspace($request), $newOwner);

        return back();
    }

    public function leave(LeaveWorkspaceRequest $request, LeaveWorkspace $leaveWorkspace): RedirectResponse
    {
        $user = $request->user();
        $leaveWorkspace->execute($user, $this->currentWorkspace($request));

        return $this->redirectAfterLeavingWorkspace($user);
    }

    public function destroy(DeleteWorkspaceRequest $request, DeleteWorkspace $deleteWorkspace): RedirectResponse
    {
        $user = $request->user();
        $deleteWorkspace->execute($user, $this->currentWorkspace($request));

        return $this->redirectAfterLeavingWorkspace($user);
    }

    public function removeMember(RemoveMemberRequest $request, User $member, RemoveMember $removeMember): RedirectResponse
    {
        $removeMember->execute($request->user(), $this->currentWorkspace($request), $member);

        return back();
    }

    public function import(ImportWorkspaceBackupRequest $request, ImportWorkspaceBackup $import): RedirectResponse
    {
        $data = $request->validated();
        $contents = file_get_contents($data['file']->getRealPath());
        $payload = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'file' => 'Некорректный JSON.',
            ]);
        }

        $workspace = $this->currentWorkspace($request);
        $replace = ($data['replace'] ?? false) ? $workspace : null;

        $import->execute($request->user(), $payload, $replace);

        return redirect()->route('overview');
    }

    public function switch(Workspace $workspace, SwitchWorkspace $switchWorkspace): RedirectResponse
    {
        $switchWorkspace->execute(request()->user(), $workspace);

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

    private function redirectAfterLeavingWorkspace(User $user): RedirectResponse
    {
        $user->refresh();
        $user->unsetRelation('memberships');

        if ($user->resolveCurrentWorkspace() === null) {
            return redirect()->route('workspaces.create');
        }

        return redirect()->route('overview');
    }
}
