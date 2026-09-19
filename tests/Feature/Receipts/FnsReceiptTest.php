<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Support\FnsQr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
});

it('parses a fiscal qr payload', function () {
    $parsed = FnsQr::parse('t=20180812T2008&s=76.40&fn=8710000101375795&i=4901&fp=3307350167&n=1');

    expect($parsed['fn'])->toBe('8710000101375795')
        ->and($parsed['i'])->toBe('4901')
        ->and($parsed['fp'])->toBe('3307350167');
});

it('rejects a string without fiscal fields', function () {
    FnsQr::parse('https://example.com');
})->throws(ValidationException::class);

it('parses fiscal fields from an ofd url', function () {
    $parsed = FnsQr::parse('https://consumer.1-ofd.ru/v1?t=20240115T1830&s=128.50&fn=9285000100206366&i=34929&fp=3951774668&n=1');

    expect($parsed['t'])->toBe('20240115T1830')
        ->and($parsed['fn'])->toBe('9285000100206366')
        ->and($parsed['fp'])->toBe('3951774668');
});

it('explains when the qr is not a fiscal receipt code', function () {
    try {
        FnsQr::parse('26230137820000014150920261506');
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()['qr'][0])->toContain('не фискальный');
    }
});

it('renders the receipt page without a cabinet', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('receipts/Index')
            ->where('connected', false)
            ->where('receipt', null)
            ->has('accounts')
            ->has('expenseCategories', 7));
});

it('looks up receipt items from the fns ticket api', function () {
    config([
        'services.fns.base_url' => 'https://irkkt-mobile.nalog.ru:8888',
    ]);

    Http::fake([
        'https://irkkt-mobile.nalog.ru:8888/v2/mobile/users/lkfl/auth' => Http::response(['sessionId' => 'sess-1'], 200),
        'https://irkkt-mobile.nalog.ru:8888/v2/ticket' => Http::response(['id' => 'ticket-1'], 200),
        'https://irkkt-mobile.nalog.ru:8888/v2/tickets/ticket-1' => Http::response([
            'seller' => ['name' => 'ООО Тест', 'inn' => '7700000000'],
            'operation' => ['date' => '2024-01-15T18:30:00+03:00', 'sum' => 12850],
            'ticket' => [
                'document' => [
                    'receipt' => [
                        'items' => [
                            ['name' => 'Молоко', 'quantity' => 1, 'price' => 8900, 'sum' => 8900],
                            ['name' => 'Хлеб', 'quantity' => 1, 'price' => 3950, 'sum' => 3950],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'fns_inn' => '7700000000',
        'fns_password' => 'secret',
    ]);
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $food = $workspace->categories()->where('name', 'Продукты')->first();

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->post(route('receipts.lookup'), [
            'qr' => 't=20240115T1830&s=128.50&fn=9285000100206366&i=34929&fp=3951774668&n=1',
        ])
        ->assertRedirect(route('receipts.index'));

    $this->actingAs($user)
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('receipts/Index')
            ->where('receipt.seller', 'ООО Тест')
            ->where('receipt.items.0.name', 'Молоко')
            ->where('receipt.items.0.suggested_category_id', $food->id)
            ->where('receipt.items.1.sum', 3950)
            ->where('receipt.occurred_on', '2024-01-15'));
});

it('rejects a lookup without fns credentials', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->post(route('receipts.lookup'), [
            'qr' => 't=20240115T1830&s=128.50&fn=9285000100206366&i=34929&fp=3951774668&n=1',
        ])
        ->assertRedirect(route('receipts.index'))
        ->assertSessionHasErrors('qr');
});
