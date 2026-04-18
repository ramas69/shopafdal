<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case CLIENT_MANAGER = 'ROLE_CLIENT_MANAGER';
}
