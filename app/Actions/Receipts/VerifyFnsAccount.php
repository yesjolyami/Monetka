<?php

namespace App\Actions\Receipts;

use App\Models\User;
use App\Support\FnsReceiptClient;

class VerifyFnsAccount
{
    public function __construct(private FnsReceiptClient $fnsReceiptClient) {}

    public function execute(User $user): bool
    {
        if (! $user->hasFnsCredentials()) {
            return false;
        }

        return $this->fnsReceiptClient->attempt(
            (string) $user->fns_inn,
            (string) $user->fns_password,
        );
    }
}
