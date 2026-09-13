<?php

namespace App\Http\Controllers;

use App\Actions\Export\ExportTransactionsCsv;
use App\Actions\Export\ExportWorkspaceBackup;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function csv(Request $request, ExportTransactionsCsv $export): StreamedResponse
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        return $export->execute($workspace, $this->resolvedMonth($request));
    }

    public function json(Request $request, ExportWorkspaceBackup $export): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        return response()->json(
            $export->execute($workspace),
            200,
            ['Content-Disposition' => 'attachment; filename="monetka-backup.json"'],
            JSON_UNESCAPED_UNICODE,
        );
    }

    private function resolvedMonth(Request $request): string
    {
        $month = $request->string('month')->toString();

        if ($month === 'all' || preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return $month;
        }

        return now()->format('Y-m');
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
