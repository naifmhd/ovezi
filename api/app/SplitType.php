<?php

namespace App;

enum SplitType: string
{
    case Equal = 'equal';
    case Exact = 'exact';
    case Percentage = 'percentage';
    case Shares = 'shares';
}
