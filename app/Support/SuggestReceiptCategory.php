<?php

namespace App\Support;

final class SuggestReceiptCategory
{
    /**
     * @var array<string, list<string>>
     */
    private const KEYWORDS = [
        'Одежда' => ['футболк', 'майк', 'джинс', 'куртк', 'плать', 'обув', 'кроссов', 'пакет', 'zolla', 'одежд', 'рубаш', 'свитер', 'носки'],
        'Кафе' => ['кофе', 'капуч', 'латте', 'кафе', 'ресторан', 'пицц', 'бургер', 'роллы', 'суши', 'макдак', 'kfc'],
        'Транспорт' => ['такси', 'метро', 'бензин', 'проезд', 'автобус', 'парков', 'яндекс.такси', 'uber'],
        'Здоровье' => ['аптек', 'лекар', 'врач', 'таблет', 'мазь'],
        'Жильё' => ['аренда', 'коммунал', 'жкх', 'свет', 'газ', 'вода'],
        'Подписки' => ['подписк', 'netflix', 'spotify', 'youtube', 'иви'],
        'Продукты' => ['молок', 'хлеб', 'яйц', 'сыр', 'мясо', 'овощ', 'фрукт', 'банан', 'яблок', 'крупа', 'сахар', 'соль', 'масло'],
    ];

    public static function nameFor(string $itemName): string
    {
        $haystack = mb_strtolower($itemName);

        foreach (self::KEYWORDS as $category => $needles) {
            foreach ($needles as $needle) {
                if (mb_strpos($haystack, $needle) !== false) {
                    return $category;
                }
            }
        }

        return 'Продукты';
    }
}
