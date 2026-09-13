<?php

namespace App\Support;

use App\Enums\CategoryKind;
use App\Models\Workspace;

class DefaultCategories
{
    public static function seed(Workspace $workspace): void
    {
        foreach (self::defaults() as $category) {
            $workspace->categories()->create($category);
        }
    }

    /**
     * @return list<array{kind: CategoryKind, name: string, emoji: string, color: string}>
     */
    private static function defaults(): array
    {
        return [
            ['kind' => CategoryKind::Expense, 'name' => 'Продукты', 'emoji' => '🥑', 'color' => '#00CC4B'],
            ['kind' => CategoryKind::Expense, 'name' => 'Транспорт', 'emoji' => '🚌', 'color' => '#1C6CFF'],
            ['kind' => CategoryKind::Expense, 'name' => 'Жильё', 'emoji' => '🏠', 'color' => '#7F8BA4'],
            ['kind' => CategoryKind::Expense, 'name' => 'Кафе', 'emoji' => '☕️', 'color' => '#FECE4C'],
            ['kind' => CategoryKind::Expense, 'name' => 'Подписки', 'emoji' => '🎬', 'color' => '#000814'],
            ['kind' => CategoryKind::Expense, 'name' => 'Здоровье', 'emoji' => '💊', 'color' => '#FF4433'],
            ['kind' => CategoryKind::Expense, 'name' => 'Одежда', 'emoji' => '👕', 'color' => '#1C6CFF'],
            ['kind' => CategoryKind::Income, 'name' => 'Зарплата', 'emoji' => '💼', 'color' => '#00CC4B'],
            ['kind' => CategoryKind::Income, 'name' => 'Подработка', 'emoji' => '🛠️', 'color' => '#1C6CFF'],
            ['kind' => CategoryKind::Income, 'name' => 'Подарки', 'emoji' => '🎁', 'color' => '#FECE4C'],
        ];
    }
}
