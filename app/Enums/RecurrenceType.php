<?php

namespace App\Enums;

enum RecurrenceType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}
