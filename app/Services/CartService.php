<?php

namespace App\Services;

class CartService
{
    private const SESSION_KEY = 'cart_items';

    public function items(): array
    {
        return $this->normalize(session(self::SESSION_KEY) ?? []);
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

    public function update(string $productUid, int $quantity): void
    {
        $items = $this->items();

        if ($quantity <= 0) {
            unset($items[$productUid]);
        } else {
            $items[$productUid] = [
                'product_uid' => $productUid,
                'quantity'    => $quantity,
            ];
        }

        session()->set(self::SESSION_KEY, $items);
    }

    public function remove(string $productUid): void
    {
        $items = $this->items();
        unset($items[$productUid]);

        session()->set(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        session()->remove(self::SESSION_KEY);
    }

    public function count(): int
    {
        return array_sum(array_map(
            static fn (array $item): int => (int) $item['quantity'],
            $this->items(),
        ));
    }

    private function normalize(array $items): array
    {
        $normalized = [];

        foreach ($items as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productUid = (string) ($item['product_uid'] ?? $key);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($productUid === '') {
                continue;
            }

            $normalized[$productUid] = [
                'product_uid' => $productUid,
                'quantity'    => $quantity,
            ];
        }

        return $normalized;
    }
}
