<?php 

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspend = 'suspend';
    case Banned = 'banned';
}