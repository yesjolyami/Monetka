<?php

namespace App\Http\Requests\Transactions;

use App\Enums\CategoryKind;

class StoreIncomeRequest extends StoreMoneyMovementRequest
{
    protected function categoryKind(): CategoryKind
    {
        return CategoryKind::Income;
    }
}
