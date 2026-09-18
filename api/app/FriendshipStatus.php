<?php

namespace App;

enum FriendshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
}
