<?php

namespace App\Services;

class CartService
{
    private const SESSION_KEY = 'cart_items';

    public function items(): array
    {
        return session(self::SESSION_KEY) ?? [];
    }

    public function add(int $productId, int $quantity): void
    {
        $items = $this->items();
        $items[$productId] = [
            'product_id' => $productId,
            'quantity'   => max(1, ($items[$productId]['quantity'] ?? 0) + $quantity),
        ];

        session()->set(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        session()->remove(self::SESSION_KEY);
    }
}
