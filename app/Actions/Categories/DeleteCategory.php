<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function execute(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            if ($category->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Нельзя удалить категорию: по ней есть операции.',
                ]);
            }

            $category->delete();
        });
    }
}
