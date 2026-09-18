<?php

namespace App;

enum ExchangeRateSource: string
{
    case SameCurrency = 'same_currency';
    case Provider = 'provider';
    case Group = 'group';
    case Expense = 'expense';
}
