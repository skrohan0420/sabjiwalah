<?php

namespace App\Services;

class CartService
{
    private const SESSION_KEY = 'cart_items';

    public function items(): array
    {
        return session(self::SESSION_KEY) ?? [];
    }

    public function add(string $productUid, int $quantity): void
    {
        $items = $this->items();
        $items[$productUid] = [
            'product_uid' => $productUid,
            'quantity'    => max(1, ($items[$productUid]['quantity'] ?? 0) + $quantity),
        ];

        session()->set(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        session()->remove(self::SESSION_KEY);
    }
}
