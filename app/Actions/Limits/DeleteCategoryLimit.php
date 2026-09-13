<?php

namespace App\Actions\Limits;

use App\Models\CategoryLimit;
use Illuminate\Support\Facades\DB;

class DeleteCategoryLimit
{
    public function execute(CategoryLimit $limit): void
    {
        DB::transaction(function () use ($limit): void {
            $limit->delete();
        });
    }
}
