<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'workspace' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                $workspace = $request->attributes->get('workspace') ?? $user->resolveCurrentWorkspace();
                if (! $workspace) {
                    return null;
                }

                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'currency' => $workspace->currency,
                    'role' => $workspace->roleFor($user),
                ];
            },
            'workspaces' => fn () => $request->user()
                ? $request->user()->workspaces()->orderBy('name')->get(['workspaces.id', 'name', 'currency'])
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
