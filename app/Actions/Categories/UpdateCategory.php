<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class UpdateCategory
{
    /**
     * @param  array{kind: string, name: string, emoji: string, color: string}  $data
     */
    public function execute(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update([
                'kind' => $data['kind'],
                'name' => $data['name'],
                'emoji' => $data['emoji'],
                'color' => $data['color'],
            ]);

            return $category;
        });
    }
}
