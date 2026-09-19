<?php

namespace App\Actions\Receipts;

use App\Models\User;
use App\Support\FnsReceiptClient;

class ConnectFnsAccount
{
    public function __construct(private FnsReceiptClient $fnsReceiptClient) {}

    public function execute(User $user, string $inn, string $password): void
    {
        $this->fnsReceiptClient->login($inn, $password);

        $user->forceFill([
            'fns_inn' => $inn,
            'fns_password' => $password,
        ])->save();
    }
}
