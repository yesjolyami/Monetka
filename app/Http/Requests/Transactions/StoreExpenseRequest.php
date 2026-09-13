<?php

namespace App\Http\Requests\Transactions;

use App\Enums\CategoryKind;

class StoreExpenseRequest extends StoreMoneyMovementRequest
{
    protected function categoryKind(): CategoryKind
    {
        return CategoryKind::Expense;
    }
}
