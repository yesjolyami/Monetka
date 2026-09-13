<?php

namespace App\Actions\Banks;

use App\Models\Bank;
use Illuminate\Support\Facades\DB;

class UpdateBank
{
    /**
     * @param  array{name: string, color?: string|null}  $data
     */
    public function execute(Bank $bank, array $data): Bank
    {
        return DB::transaction(function () use ($bank, $data) {
            $bank->update([
                'name' => $data['name'],
                'color' => $data['color'] ?? null,
            ]);

            return $bank;
        });
    }
}
