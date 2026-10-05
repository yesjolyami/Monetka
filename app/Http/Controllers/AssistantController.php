<?php

namespace App\Http\Controllers;

use App\Actions\Assistant\AskAssistant;
use App\Http\Requests\Assistant\AskAssistantRequest;
use App\Models\Workspace;
use App\Support\AssistantSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        $key = config('services.ai.key') ?: config('services.openrouter.key');

        return Inertia::render('assistant/Index', [
            'snapshot' => AssistantSnapshot::for($workspace),
            'model' => config('services.ai.model') ?: config('services.openrouter.model'),
            'provider' => config('services.ai.provider', 'openrouter'),
            'configured' => is_string($key) && $key !== '',
        ]);
    }

    public function store(AskAssistantRequest $request, AskAssistant $askAssistant): JsonResponse
    {
        $data = $request->validated();

        $reply = $askAssistant->execute(
            $this->currentWorkspace($request),
            $data['message'],
            $data['history'] ?? [],
        );

        return response()->json($reply);
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
