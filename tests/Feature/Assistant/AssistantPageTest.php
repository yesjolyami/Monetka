<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AssistantPrompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

it('redirects guests away from the assistant', function () {
    $this->get(route('assistant.index'))
        ->assertRedirect(route('login'));
});

it('shows the assistant snapshot for a member', function () {
    config(['services.openrouter.key' => null]);

    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 500000,
    ]);

    $this->actingAs($user)
        ->get(route('assistant.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('assistant/Index')
            ->where('snapshot.workspace_name', 'Семья')
            ->where('snapshot.currency', 'RUB')
            ->where('snapshot.total_visible', 500000)
            ->where('configured', false)
            ->where('model', 'qwen/qwen3.8-27b:free'));
});

it('puts snapshot numbers and write ban into the system prompt', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $prompt = AssistantPrompt::system([
        'workspace_name' => $workspace->name,
        'total_visible' => 500000,
    ]);

    expect($prompt)
        ->toContain('Не выдумывай')
        ->toContain('не меняешь остатки')
        ->toContain('Запрещено писать «записал»')
        ->toContain('Семья')
        ->toContain('500000')
        ->toContain('"text"');
});

it('asks the model with the snapshot and does not write the journal', function () {
    config([
        'services.openrouter.key' => 'test-key',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        'services.openrouter.model' => 'qwen/qwen3.8-27b:free',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'text' => 'В этом месяце расходов нет.',
                        'draft' => null,
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 500000,
    ]);

    $this->actingAs($user)
        ->postJson(route('assistant.store'), [
            'message' => 'Куда ушли деньги?',
        ])
        ->assertOk()
        ->assertJson([
            'text' => 'В этом месяце расходов нет.',
            'draft' => null,
        ]);

    Http::assertSent(function ($request) {
        $messages = $request['messages'] ?? [];
        $system = $messages[0]['content'] ?? '';

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && str_contains($system, 'Семья')
            && str_contains($system, 'Не выдумывай')
            && ($request['messages'][1]['content'] ?? '') === 'Куда ушли деньги?';
    });

    expect(Transaction::query()->where('type', 'expense')->count())->toBe(0);
});

it('routes assistant requests through the selected compatible aggregator', function () {
    config([
        'services.ai.provider' => 'proxyapi',
        'services.ai.key' => 'proxy-key',
        'services.ai.base_url' => 'https://api.proxyapi.ru/v1',
        'services.ai.model' => 'openai/gpt-5-mini',
        'services.openrouter.key' => null,
    ]);

    Http::fake([
        'https://api.proxyapi.ru/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'text' => 'Ответ через ProxyAPI.',
                        'draft' => null,
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ]),
    ]);

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->postJson(route('assistant.store'), ['message' => 'Проверка'])
        ->assertOk()
        ->assertJsonPath('text', 'Ответ через ProxyAPI.');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.proxyapi.ru/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer proxy-key')
        && $request['model'] === 'openai/gpt-5-mini'
        && ! isset($request['provider']));
});

it('returns a draft without creating an expense', function () {
    config([
        'services.openrouter.key' => 'test-key',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'text' => 'Это черновик, не запись.',
                        'draft' => [
                            'type' => 'expense',
                            'amount_minor' => 35000,
                            'account_name' => 'Карта',
                            'category_name' => 'Кафе',
                            'date' => '2026-09-28',
                            'description' => 'кофе',
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('assistant.store'), [
            'message' => 'кофе 350 с карты',
        ])
        ->assertOk()
        ->assertJsonPath('draft.amount', 35000)
        ->assertJsonPath('draft.account_id', $account->id)
        ->assertJsonPath('draft.type', 'Расход');

    expect(Transaction::query()->where('type', 'expense')->count())->toBe(0);
});

it('rejects chat without a key', function () {
    config(['services.openrouter.key' => null]);

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->postJson(route('assistant.store'), [
            'message' => 'Куда ушли деньги?',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

it('falls back to the free router when the primary model is rate limited', function () {
    config([
        'services.openrouter.key' => 'test-key',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        'services.openrouter.model' => 'qwen/qwen3.8-27b:free',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
            ->push(['error' => ['message' => 'rate-limited', 'code' => 429]], 429)
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'text' => 'Лимитов нет.',
                            'draft' => null,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200),
    ]);

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->postJson(route('assistant.store'), [
            'message' => 'Какой лимит на подписочки',
        ])
        ->assertOk()
        ->assertJsonPath('text', 'Лимитов нет.');

    Http::assertSentCount(2);
});

it('explains a timeout instead of blaming the internet', function () {
    config([
        'services.openrouter.key' => 'test-key',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        'services.openrouter.model' => 'openrouter/free',
    ]);

    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 45016 milliseconds with 165 bytes received');
    });

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->postJson(route('assistant.store'), [
            'message' => 'Куда ушли деньги?',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message')
        ->assertJsonPath('errors.message.0', 'OpenRouter не успел ответить. Бесплатная очередь зависла — подождите минуту и спросите снова.');
});

it('keeps the rate-limit message when the fallback model hangs', function () {
    config([
        'services.openrouter.key' => 'test-key',
        'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        'services.openrouter.model' => 'qwen/qwen3.8-27b:free',
    ]);

    $calls = 0;

    Http::fake(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            return Http::response(['error' => ['message' => 'rate-limited']], 429);
        }

        throw new ConnectionException('cURL error 28: Operation timed out after 45016 milliseconds with 165 bytes received');
    });

    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->postJson(route('assistant.store'), [
            'message' => 'Какой лимит на подписочки',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.message.0', 'Бесплатная очередь OpenRouter перегружена. Подождите минуту и спросите снова.');
});

it('records an assistant draft of 1500 rubles without multiplying kopecks again', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Нал',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $category = $workspace->categories()->where('kind', 'expense')->where('name', 'Одежда')->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactions.expense'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '1500.00',
            'occurred_on' => '2026-09-01',
            'description' => 'футболка',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Transaction::query()->where('description', 'футболка')->value('amount'))->toBe(150000);
});
