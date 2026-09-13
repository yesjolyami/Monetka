<?php

namespace App\Actions\Banks;

use App\Models\Bank;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateBank
{
    /**
     * @param  array{name: string, color?: string|null}  $data
     */
    public function execute(Workspace $workspace, array $data): Bank
    {
        return DB::transaction(function () use ($workspace, $data) {
            return Bank::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'color' => $data['color'] ?? null,
            ]);
        });
    }
}
