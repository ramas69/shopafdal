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
}
