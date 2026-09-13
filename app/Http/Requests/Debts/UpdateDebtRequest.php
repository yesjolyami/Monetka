<?php

namespace App\Http\Requests\Debts;

use App\Models\Debt;

class UpdateDebtRequest extends StoreDebtRequest
{
    public function authorize(): bool
    {
        $debt = $this->route('debt');

        return $debt instanceof Debt
            && $this->user()?->can('update', $debt);
    }
}
