<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\WorkspaceStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        $start = Carbon::parse($this->resolvedMonth($request).'-01')->startOfMonth();

        return Inertia::render('stats/Index', [
            'year' => $start->year,
            'month' => $start->month,
            'monthKey' => $start->format('Y-m'),
            'stats' => WorkspaceStatistics::for($workspace, $start->year, $start->month),
        ]);
    }

    private function resolvedMonth(Request $request): string
    {
        $month = $request->string('month')->toString();

        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return now()->format('Y-m');
        }

        return $month;
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
