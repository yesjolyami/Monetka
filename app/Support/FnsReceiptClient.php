<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FnsReceiptClient
{
    /**
     * @return array{
     *     seller: string|null,
     *     inn: string|null,
     *     datetime: string|null,
     *     total: int|null,
     *     items: list<array{name: string, quantity: string, price: int, sum: int}>
     * }
     */
    public function fetch(string $qr, string $inn, string $password): array
    {
        $sessionId = $this->login($inn, $password);
        $ticketId = $this->ticketId($sessionId, $qr);
        $payload = $this->ticket($sessionId, $ticketId);

        return $this->normalize($payload);
    }

    public function attempt(string $inn, string $password): bool
    {
        try {
            $this->login($inn, $password);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function login(string $inn, string $password): string
    {
        if ($inn === '' || $password === '') {
            throw ValidationException::withMessages([
                'password' => 'Укажите ИНН и пароль личного кабинета nalog.ru.',
            ]);
        }

        $response = $this->http()
            ->post($this->url('/v2/mobile/users/lkfl/auth'), [
                'inn' => $inn,
                'password' => $password,
                'client_secret' => (string) config('services.fns.client_secret'),
            ]);

        if (! $response->successful() || ! is_string($response->json('sessionId'))) {
            throw ValidationException::withMessages([
                'password' => 'ФНС не пустила в кабинет. Проверьте ИНН и пароль.',
            ]);
        }

        return $response->json('sessionId');
    }

    private function ticketId(string $sessionId, string $qr): string
    {
        $response = $this->http($sessionId)
            ->post($this->url('/v2/ticket'), ['qr' => $qr]);

        $id = $response->json('id');

        if (! $response->successful() || ! is_string($id) || $id === '') {
            throw ValidationException::withMessages([
                'qr' => 'ФНС не приняла QR. Чек мог быть ещё не загружен в облако ККТ.',
            ]);
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticket(string $sessionId, string $ticketId): array
    {
        $response = $this->http($sessionId)
            ->get($this->url('/v2/tickets/'.$ticketId));

        if (! $response->successful() || ! is_array($response->json())) {
            throw ValidationException::withMessages([
                'qr' => 'ФНС не вернула состав чека. Повторите через минуту.',
            ]);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     seller: string|null,
     *     inn: string|null,
     *     datetime: string|null,
     *     total: int|null,
     *     items: list<array{name: string, quantity: string, price: int, sum: int}>
     * }
     */
    private function normalize(array $payload): array
    {
        $seller = $payload['seller'] ?? null;
        $receipt = data_get($payload, 'ticket.document.receipt');
        $receipt = is_array($receipt) ? $receipt : [];

        $items = [];
        $rawItems = $receipt['items'] ?? [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = is_string($item['name'] ?? null) ? $item['name'] : 'Позиция';
                $quantity = $item['quantity'] ?? 1;
                $price = (int) ($item['price'] ?? 0);
                $sum = (int) ($item['sum'] ?? $price);

                $items[] = [
                    'name' => $name,
                    'quantity' => is_scalar($quantity) ? (string) $quantity : '1',
                    'price' => $price,
                    'sum' => $sum,
                ];
            }
        }

        $sellerName = is_array($seller) && is_string($seller['name'] ?? null)
            ? $seller['name']
            : (is_string($receipt['user'] ?? null) ? $receipt['user'] : null);

        $inn = is_array($seller) && is_string($seller['inn'] ?? null)
            ? $seller['inn']
            : (is_string($receipt['userInn'] ?? null) ? $receipt['userInn'] : null);

        $total = data_get($payload, 'operation.sum', $receipt['totalSum'] ?? null);

        return [
            'seller' => $sellerName,
            'inn' => $inn,
            'datetime' => is_string(data_get($payload, 'operation.date'))
                ? (string) data_get($payload, 'operation.date')
                : null,
            'total' => is_numeric($total) ? (int) $total : null,
            'items' => $items,
        ];
    }

    private function http(?string $sessionId = null): PendingRequest
    {
        $headers = [
            'Device-OS' => 'iOS',
            'Device-Id' => (string) config('services.fns.device_id'),
            'clientVersion' => '2.9.0',
            'User-Agent' => 'billchecker/2.9.0 (iPhone; iOS 13.6; Scale/2.00)',
            'Accept-Language' => 'ru-RU',
        ];

        if ($sessionId !== null) {
            $headers['sessionId'] = $sessionId;
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->withHeaders($headers);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.fns.base_url'), '/').$path;
    }
}
