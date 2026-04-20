<?php

namespace App\Enum;

enum CompanyRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Responsable',
            self::MEMBER => 'Membre',
        };
    }
}
