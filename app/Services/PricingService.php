<?php

namespace App\Services;

use App\Models\ProductModel;

class PricingService
{
    public function calculateCart(array $cartItems): array
    {
        $products = new ProductModel();
        $subtotal = 0.0;
        $lines = [];

        foreach ($cartItems as $item) {
            $product = isset($item['product_uid'])
                ? $products->findByUid((string) $item['product_uid'])
                : $products->find((int) $item['product_id']);

            if (! $product || ! $product['is_active']) {
                continue;
            }

            $quantity = max(1, (int) $item['quantity']);
            $unitPrice = (float) ($product['sale_price'] ?? $product['price']);
            $total = $unitPrice * $quantity;
            $subtotal += $total;

            $lines[] = [
                'product'    => $product,
                'product_uid' => $product['uid'],
                'quantity'   => $quantity,
                'unit_price' => $unitPrice,
                'total'      => $total,
            ];
        }

        return [
            'lines'    => $lines,
            'subtotal' => $subtotal,
        ];
    }
}
