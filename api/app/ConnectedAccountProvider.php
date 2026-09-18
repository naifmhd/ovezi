<?php

namespace App;

enum ConnectedAccountProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
}
