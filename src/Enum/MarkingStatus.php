<?php

namespace App\Enum;

enum MarkingStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'À valider',
            self::APPROVED => 'Validé',
            self::REJECTED => 'À refaire',
        };
    }
}
