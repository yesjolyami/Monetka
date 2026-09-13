<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateCategory
{
    /**
     * @param  array{kind: string, name: string, emoji: string, color: string}  $data
     */
    public function execute(Workspace $workspace, array $data): Category
    {
        return DB::transaction(function () use ($workspace, $data) {
            return $workspace->categories()->create([
                'kind' => $data['kind'],
                'name' => $data['name'],
                'emoji' => $data['emoji'],
                'color' => $data['color'],
            ]);
        });
    }
}
