<?php

namespace App;

enum GroupMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
