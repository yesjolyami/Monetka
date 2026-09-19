<?php

use App\Support\SuggestReceiptCategory;

it('suggests clothing for apparel names', function () {
    expect(SuggestReceiptCategory::nameFor('Футболка (016323273021,L,69A3)'))->toBe('Одежда')
        ->and(SuggestReceiptCategory::nameFor('Пакет Zolla (015110C00005,S,#01)'))->toBe('Одежда');
});

it('suggests groceries for food names', function () {
    expect(SuggestReceiptCategory::nameFor('Молоко'))->toBe('Продукты')
        ->and(SuggestReceiptCategory::nameFor('Хлеб'))->toBe('Продукты');
});

it('suggests cafe and transport from keywords', function () {
    expect(SuggestReceiptCategory::nameFor('Капучино'))->toBe('Кафе')
        ->and(SuggestReceiptCategory::nameFor('Такси Яндекс'))->toBe('Транспорт');
});
