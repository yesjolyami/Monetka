<?php

namespace App\Actions\Limits;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\CategoryLimit;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertCategoryLimit
{
    public function execute(Workspace $workspace, Category $category, int $amount): CategoryLimit
    {
        return DB::transaction(function () use ($workspace, $category, $amount) {
            if ($category->workspace_id !== $workspace->id) {
                throw ValidationException::withMessages([
                    'category_id' => 'Категория не найдена в этом бюджете.',
                ]);
            }

            if ($category->kind !== CategoryKind::Expense) {
                throw ValidationException::withMessages([
                    'category_id' => 'Лимит можно задать только для категории расходов.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Сумма должна быть больше нуля.',
                ]);
            }

            return CategoryLimit::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'category_id' => $category->id,
                ],
                [
                    'amount' => $amount,
                ],
            );
        });
    }
}
