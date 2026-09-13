<?php

namespace App\Enums;

enum DebtDirection: string
{
    case TheyOwe = 'they_owe';
    case IOwe = 'i_owe';
}
