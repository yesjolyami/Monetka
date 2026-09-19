<?php

namespace App\Actions\Receipts;

use App\Models\User;
use App\Support\FnsQr;
use App\Support\FnsReceiptClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class LookupFnsReceipt
{
    public function __construct(
        private DecodeReceiptQr $decodeReceiptQr,
        private FnsReceiptClient $fnsReceiptClient,
    ) {}

    /**
     * @return array{
     *     qr: string,
     *     fiscal: array{t: string, s: string, fn: string, i: string, fp: string, n: string},
     *     seller: string|null,
     *     inn: string|null,
     *     datetime: string|null,
     *     total: int|null,
     *     items: list<array{name: string, quantity: string, price: int, sum: int}>
     * }
     */
    public function execute(User $user, ?string $qr, ?UploadedFile $photo): array
    {
        if (! $user->hasFnsCredentials()) {
            throw ValidationException::withMessages([
                'qr' => 'Сначала войдите в кабинет ФНС — укажите ИНН и пароль nalog.ru.',
            ]);
        }

        if (is_string($qr) && trim($qr) !== '') {
            $raw = $qr;
        } elseif ($photo instanceof UploadedFile) {
            $raw = $this->decodeReceiptQr->execute($photo);
        } else {
            throw ValidationException::withMessages([
                'qr' => 'Вставьте строку из QR-кода чека или загрузите фото.',
            ]);
        }

        $fiscal = FnsQr::parse($raw);
        $ticket = $this->fnsReceiptClient->fetch(
            $fiscal['qr'],
            (string) $user->fns_inn,
            (string) $user->fns_password,
        );

        return [
            'qr' => $fiscal['qr'],
            'fiscal' => [
                't' => $fiscal['t'],
                's' => $fiscal['s'],
                'fn' => $fiscal['fn'],
                'i' => $fiscal['i'],
                'fp' => $fiscal['fp'],
                'n' => $fiscal['n'],
            ],
            'seller' => $ticket['seller'],
            'inn' => $ticket['inn'],
            'datetime' => $ticket['datetime'],
            'total' => $ticket['total'],
            'items' => $ticket['items'],
        ];
    }
}
