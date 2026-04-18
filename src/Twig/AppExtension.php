<?php

namespace App\Twig;

use App\Enum\OrderStatus;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    #[AsTwigFilter('price')]
    public function formatPrice(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }

    #[AsTwigFunction('status_badge_class')]
    public function statusBadgeClass(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::DRAFT => 'bg-slate-50 text-slate-700 border-slate-200',
            OrderStatus::PLACED => 'bg-sky-50 text-sky-700 border-sky-200',
            OrderStatus::CONFIRMED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            OrderStatus::IN_PRODUCTION => 'bg-amber-50 text-amber-700 border-amber-200',
            OrderStatus::SHIPPED => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            OrderStatus::DELIVERED => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            OrderStatus::CANCELLED => 'bg-red-50 text-red-700 border-red-200',
        };
    }

    #[AsTwigFunction('status_dot_class')]
    public function statusDotClass(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::DRAFT => 'bg-slate-400',
            OrderStatus::PLACED => 'bg-sky-500',
            OrderStatus::CONFIRMED => 'bg-emerald-500',
            OrderStatus::IN_PRODUCTION => 'bg-amber-500',
            OrderStatus::SHIPPED => 'bg-indigo-500',
            OrderStatus::DELIVERED => 'bg-emerald-600',
            OrderStatus::CANCELLED => 'bg-red-500',
        };
    }

    /**
     * Returns [bgClass, textClass] for the status tile (colored icon square).
     * @return array{0:string,1:string}
     */
    #[AsTwigFunction('status_tile_classes')]
    public function statusTileClasses(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::DRAFT => ['bg-slate-100', 'text-slate-500'],
            OrderStatus::PLACED => ['bg-sky-50', 'text-sky-600'],
            OrderStatus::CONFIRMED => ['bg-emerald-50', 'text-emerald-600'],
            OrderStatus::IN_PRODUCTION => ['bg-amber-50', 'text-amber-600'],
            OrderStatus::SHIPPED => ['bg-indigo-50', 'text-indigo-600'],
            OrderStatus::DELIVERED => ['bg-emerald-100', 'text-emerald-700'],
            OrderStatus::CANCELLED => ['bg-red-50', 'text-red-600'],
        };
    }
}
