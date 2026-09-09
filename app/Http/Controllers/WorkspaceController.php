<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\SwitchWorkspace;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;

class WorkspaceController extends Controller
{
    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $data = $request->validated();

        $createWorkspace->execute($request->user(), $data['name'], $data['currency']);

        return redirect()->route('dashboard');
    }

    public function switch(Workspace $workspace, SwitchWorkspace $switchWorkspace): RedirectResponse
    {
        $switchWorkspace->execute(request()->user(), $workspace);

        return back();
    }
}
