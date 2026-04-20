<?php

namespace App\Enum;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case PLACED = 'placed';
    case CONFIRMED = 'confirmed';
    case IN_PRODUCTION = 'in_production';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PLACED => 'Placée',
            self::CONFIRMED => 'Confirmée',
            self::IN_PRODUCTION => 'En production',
            self::SHIPPED => 'Expédiée',
            self::DELIVERED => 'Livrée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function progressPct(): int
    {
        return match ($this) {
            self::DRAFT => 0,
            self::PLACED => 15,
            self::CONFIRMED => 30,
            self::IN_PRODUCTION => 60,
            self::SHIPPED => 85,
            self::DELIVERED => 100,
            self::CANCELLED => 0,
        };
    }

    public function progressColor(): string
    {
        return match ($this) {
            self::DRAFT => '#94979C',
            self::PLACED => '#6366F1',
            self::CONFIRMED => '#EC4899',
            self::IN_PRODUCTION => '#F59E0B',
            self::SHIPPED => '#0EA5E9',
            self::DELIVERED => '#10B981',
            self::CANCELLED => '#E82538',
        };
    }
}
