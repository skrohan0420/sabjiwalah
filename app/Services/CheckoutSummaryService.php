<?php

namespace App\Services;

class CheckoutSummaryService
{
    public function summary(): array
    {
        $cart = new CartService();
        $pricing = (new PricingService())->calculateCart($cart->items());
        $code = (string)(session('checkout_offer') ?? '');
        $offerError = null;
        try { $quote = $this->quote($pricing, $code); }
        catch (\InvalidArgumentException $e) {
            $quote = $this->quote($pricing, ''); $offerError = $e->getMessage();
            // Keep the selected code so order placement cannot silently ignore an invalid coupon.
            $quote['quote_token'] = null;
        }

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
            'selected_code' => $code ?: null,
            'offer_error' => $offerError,
        ] + array_diff_key($quote, ['offer' => true]);
    }

    public function quote(array $pricing, string $code = '', bool $lock = false, ?array $settings = null): array
    {
        $operations = new OperationalSettingsService();
        $settings ??= $operations->get($lock);
        $subtotal = round((float)$pricing['subtotal'], 2);
        if (!is_finite($subtotal) || $subtotal < 0 || $subtotal + (float)$settings['delivery_charge'] > 99999999.99) throw new \InvalidArgumentException('This cart total is outside the supported range.');
        $offer = $code === '' ? null : (new OfferService())->evaluate($code, $subtotal, $lock);
        $discount = $offer['discount_amount'] ?? 0.00;
        $delivery = ($settings['free_delivery_minimum'] !== null && $subtotal >= (float)$settings['free_delivery_minimum']) || $subtotal <= 0 ? 0.00 : (float)$settings['delivery_charge'];
        $notice = $operations->notice($settings, $subtotal, $lock);
        $quote = ['subtotal' => $subtotal, 'discount_amount' => $discount, 'delivery_charge' => $delivery, 'standard_delivery_charge' => (float)$settings['delivery_charge'],
            'total_amount' => round($subtotal - $discount + $delivery, 2), 'free_delivery_minimum' => $settings['free_delivery_minimum'] === null ? null : (float)$settings['free_delivery_minimum'],
            'minimum_order_amount' => (float)$settings['minimum_order_amount'], 'can_place_order' => $notice === null && $subtotal > 0, 'checkout_notice' => $notice,
            'applied_code' => $offer['code'] ?? null];
        $fingerprint = ['settings_revision' => $settings['revision'], 'totals' => $quote, 'offer' => $offer['revision'] ?? null, 'items' => array_map(static fn($line) => [
            $line['product']['uid'], (int)$line['quantity'], number_format((float)$line['unit_price'], 2, '.', '')], $pricing['lines'])];
        $quote['quote_token'] = hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR));
        return $quote + ['offer' => $offer];
    }
}
