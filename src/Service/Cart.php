<?php

namespace App\Service;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class Cart
{
    private const SESSION_KEY = 'afdal_cart';

    public function __construct(
        private RequestStack $requestStack,
        private ProductVariantRepository $variants,
    ) {}

    public function add(int $variantId, int $quantity, ?array $marking = null): void
    {
        if ($quantity < 1) {
            return;
        }
        $items = $this->rawItems();
        $items[] = [
            'line_id' => bin2hex(random_bytes(8)),
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'marking' => $marking,
        ];
        $this->setRawItems($items);
    }

    public function updateQuantity(string $lineId, int $quantity): void
    {
        $items = $this->rawItems();
        foreach ($items as $i => $item) {
            if ($item['line_id'] !== $lineId) continue;
            if ($quantity < 1) {
                unset($items[$i]);
            } else {
                $items[$i]['quantity'] = $quantity;
            }
        }
        $this->setRawItems(array_values($items));
    }

    public function remove(string $lineId): void
    {
        $items = array_values(array_filter($this->rawItems(), fn($i) => $i['line_id'] !== $lineId));
        $this->setRawItems($items);
    }

    public function clear(): void
    {
        $this->setRawItems([]);
    }

    /**
     * @return array<int, array{line_id: string, variant: ProductVariant, quantity: int, marking: ?array, unit_price_cents: int, subtotal_cents: int}>
     */
    public function lines(): array
    {
        $raw = $this->rawItems();
        if (empty($raw)) return [];

        $variantIds = array_unique(array_column($raw, 'variant_id'));
        $variants = [];
        foreach ($this->variants->findBy(['id' => $variantIds]) as $v) {
            $variants[$v->getId()] = $v;
        }

        $lines = [];
        foreach ($raw as $item) {
            $variant = $variants[$item['variant_id']] ?? null;
            if (!$variant) continue;
            $unit = $variant->getProduct()->getBasePriceCents();
            $lines[] = [
                'line_id' => $item['line_id'],
                'variant' => $variant,
                'quantity' => $item['quantity'],
                'marking' => $item['marking'] ?? null,
                'unit_price_cents' => $unit,
                'subtotal_cents' => $unit * $item['quantity'],
            ];
        }
        return $lines;
    }

    public function count(): int
    {
        $qty = 0;
        foreach ($this->rawItems() as $item) {
            $qty += $item['quantity'];
        }
        return $qty;
    }

    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines() as $line) {
            $total += $line['subtotal_cents'];
        }
        return $total;
    }

    public function isEmpty(): bool
    {
        return empty($this->rawItems());
    }

    /** @return array<int, array{line_id: string, variant_id: int, quantity: int, marking: ?array}> */
    private function rawItems(): array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, []);
    }

    private function setRawItems(array $items): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $items);
    }
}
