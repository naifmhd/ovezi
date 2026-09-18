<?php

namespace App;

enum ExpenseType: string
{
    case Group = 'group';
    case Direct = 'direct';
    case Personal = 'personal';
}
