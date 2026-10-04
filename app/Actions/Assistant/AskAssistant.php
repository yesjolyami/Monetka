<?php

namespace App\Actions\Assistant;

use App\Models\Workspace;
use App\Support\AssistantPrompt;
use App\Support\AssistantSnapshot;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AskAssistant
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{text: string, draft: array{type: string, amount: int, account: string, account_id: int|null, category: string, category_id: int|null, date: string, description: string}|null}
     */
    public function execute(Workspace $workspace, string $message, array $history = []): array
    {
        $key = config('services.openrouter.key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'message' => 'Нет ключа OpenRouter. Журнал и чек работают, чат недоступен.',
            ]);
        }

        $snapshot = AssistantSnapshot::for($workspace);
        $messages = [
            ['role' => 'system', 'content' => AssistantPrompt::system($snapshot)],
        ];

        foreach (array_slice($history, -10) as $turn) {
            $role = $turn['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = [
                'role' => $role,
                'content' => $turn['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $preferred = (string) config('services.openrouter.model');
        [$response, $connectionError] = $this->complete($key, $preferred, $messages);

        if ($response !== null && ! $response->successful() && in_array($response->status(), [404, 429], true) && $preferred !== 'openrouter/free') {
            Log::warning('OpenRouter primary model failed, falling back', [
                'model' => $preferred,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            [$fallback] = $this->complete($key, 'openrouter/free', $messages);

            if ($fallback !== null && $fallback->successful()) {
                $response = $fallback;
            }
        }

        if ($response === null) {
            throw ValidationException::withMessages([
                'message' => $this->connectionMessage($connectionError),
            ]);
        }

        if (! $response->successful()) {
            Log::warning('OpenRouter request failed', [
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);

            throw ValidationException::withMessages([
                'message' => $this->userMessage($response),
            ]);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw ValidationException::withMessages([
                'message' => 'Модель вернула пустой ответ.',
            ]);
        }

        return $this->parse($content, $snapshot);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{0: Response|null, 1: string|null}
     */
    private function complete(string $key, string $model, array $messages): array
    {
        try {
            $response = Http::baseUrl((string) config('services.openrouter.base_url'))
                ->withToken($key)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(45)
                ->withHeaders([
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-Title' => 'Monetka',
                ])
                ->post('/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'provider' => [
                        'allow_fallbacks' => true,
                    ],
                ]);

            return [$response, null];
        } catch (ConnectionException $exception) {
            Log::warning('OpenRouter connection failed', [
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);

            return [null, $exception->getMessage()];
        }
    }

    private function connectionMessage(?string $error): string
    {
        $timeout = is_string($error) && (str_contains($error, 'timed out') || str_contains($error, 'cURL error 28'));

        return $timeout
            ? 'OpenRouter не успел ответить. Бесплатная очередь зависла — подождите минуту и спросите снова.'
            : 'Нет связи с OpenRouter. Проверьте интернет и попробуйте ещё раз.';
    }

    private function userMessage(Response $response): string
    {
        return match ($response->status()) {
            401, 403 => 'Ключ OpenRouter не принят. Проверьте OPENROUTER_API_KEY.',
            404 => 'Этой модели сейчас нет в бесплатной очереди. Поставьте в .env OPENROUTER_MODEL=openrouter/free',
            429 => 'Бесплатная очередь OpenRouter перегружена. Подождите минуту и спросите снова.',
            default => 'Модель не ответила. Попробуйте ещё раз через минуту.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{text: string, draft: array{type: string, amount: int, account: string, account_id: int|null, category: string, category_id: int|null, date: string, description: string}|null}
     */
    private function parse(string $content, array $snapshot): array
    {
        $payload = $this->decode($content);
        $text = trim((string) ($payload['text'] ?? ''));

        if ($text === '') {
            throw ValidationException::withMessages([
                'message' => 'Модель ответила без текста.',
            ]);
        }

        return [
            'text' => $text,
            'draft' => $this->draft($payload['draft'] ?? null, $snapshot),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        $raw = trim($content);

        if (preg_match('/\{.*\}/s', $raw, $matches) === 1) {
            $raw = $matches[0];
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return ['text' => $content, 'draft' => null];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{type: string, amount: int, account: string, account_id: int|null, category: string, category_id: int|null, date: string, description: string}|null
     */
    private function draft(mixed $draft, array $snapshot): ?array
    {
        if (! is_array($draft)) {
            return null;
        }

        $type = (string) ($draft['type'] ?? 'expense');

        if (! in_array($type, ['expense', 'income', 'transfer'], true)) {
            return null;
        }

        $amount = (int) ($draft['amount_minor'] ?? 0);

        if ($amount < 1) {
            return null;
        }

        $account = $this->matchAccount((string) ($draft['account_name'] ?? ''), $snapshot['accounts'] ?? []);
        $category = $this->matchCategory((string) ($draft['category_name'] ?? ''), $snapshot['expense_categories'] ?? []);
        $date = (string) ($draft['date'] ?? now()->toDateString());

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = now()->toDateString();
        }

        $labels = [
            'expense' => 'Расход',
            'income' => 'Доход',
            'transfer' => 'Перевод',
        ];

        return [
            'type' => $labels[$type],
            'amount' => $amount,
            'account' => $account['name'] ?? (string) ($draft['account_name'] ?? ''),
            'account_id' => $account['id'] ?? null,
            'category' => trim(($category['emoji'] ?? '').' '.($category['name'] ?? (string) ($draft['category_name'] ?? ''))),
            'category_id' => $category['id'] ?? null,
            'date' => $date,
            'description' => mb_substr(trim((string) ($draft['description'] ?? '')), 0, 255),
        ];
    }

    /**
     * @param  list<array{id: int, name: string, balance: int}>  $accounts
     * @return array{id: int, name: string}|null
     */
    private function matchAccount(string $needle, array $accounts): ?array
    {
        $needle = mb_strtolower(trim($needle));

        if ($needle === '') {
            return $accounts[0] ?? null;
        }

        foreach ($accounts as $account) {
            if (mb_strtolower($account['name']) === $needle) {
                return $account;
            }
        }

        foreach ($accounts as $account) {
            if (str_contains(mb_strtolower($account['name']), $needle) || str_contains($needle, mb_strtolower($account['name']))) {
                return $account;
            }
        }

        return $accounts[0] ?? null;
    }

    /**
     * @param  list<array{id: int, name: string, emoji: string}>  $categories
     * @return array{id: int, name: string, emoji: string}|null
     */
    private function matchCategory(string $needle, array $categories): ?array
    {
        $needle = mb_strtolower(trim($needle));

        if ($needle === '' || $categories === []) {
            return $categories[0] ?? null;
        }

        foreach ($categories as $category) {
            if (mb_strtolower($category['name']) === $needle) {
                return $category;
            }
        }

        foreach ($categories as $category) {
            if (str_contains(mb_strtolower($category['name']), $needle) || str_contains($needle, mb_strtolower($category['name']))) {
                return $category;
            }
        }

        return $categories[0] ?? null;
    }
}
