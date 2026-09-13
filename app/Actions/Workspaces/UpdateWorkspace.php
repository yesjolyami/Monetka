<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateWorkspace
{
    /**
     * @param  array{name: string, currency: string}  $data
     */
    public function execute(User $actor, Workspace $workspace, array $data): Workspace
    {
        Gate::forUser($actor)->authorize('update', $workspace);

        if ($data['currency'] !== $workspace->currency && $workspace->transactions()->exists()) {
            throw ValidationException::withMessages([
                'currency' => 'Нельзя сменить валюту: в бюджете уже есть операции.',
            ]);
        }

        $workspace->update([
            'name' => $data['name'],
            'currency' => $data['currency'],
        ]);

        return $workspace;
    }
}
