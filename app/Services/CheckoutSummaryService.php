<?php

namespace App\Services;

class CheckoutSummaryService
{
    private const DELIVERY_CHARGE = 40.00;
    private const FREE_DELIVERY_MINIMUM = 499.00;

    public function summary(): array
    {
        $cart = new CartService();
        $pricing = (new PricingService())->calculateCart($cart->items());
        $subtotal = (float) $pricing['subtotal'];
        $discountAmount = 0.00;
        $deliveryCharge = $subtotal >= self::FREE_DELIVERY_MINIMUM || $subtotal <= 0 ? 0.00 : self::DELIVERY_CHARGE;

        return [
            'items' => array_map(
                static fn (array $line): array => [
                    'product'    => [
                        'uid'        => $line['product']['uid'],
                        'name'       => $line['product']['name'],
                        'image'      => $line['product']['image'],
                        'unit'       => $line['product']['unit'],
                        'price'      => (float) $line['product']['price'],
                        'sale_price' => $line['product']['sale_price'] === null ? null : (float) $line['product']['sale_price'],
                    ],
                    'quantity'   => (int) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'total'      => (float) $line['total'],
                ],
                $pricing['lines'],
            ),
            'count'                 => $cart->count(),
            'subtotal'              => $subtotal,
            'discount_amount'       => $discountAmount,
            'delivery_charge'       => $deliveryCharge,
            'total_amount'          => $subtotal - $discountAmount + $deliveryCharge,
            'free_delivery_minimum' => self::FREE_DELIVERY_MINIMUM,
        ];
    }
}
