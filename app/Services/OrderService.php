<?php

namespace App\Services;

use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Models\ProductModel;
use InvalidArgumentException;

class OrderService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DELIVERY_FAILED = 'delivery_failed';

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CONFIRMED => [
            self::STATUS_PREPARING,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PREPARING => [
            self::STATUS_READY_FOR_DELIVERY,
        ],
        self::STATUS_READY_FOR_DELIVERY => [
            self::STATUS_OUT_FOR_DELIVERY,
        ],
        self::STATUS_OUT_FOR_DELIVERY => [
            self::STATUS_DELIVERED,
            self::STATUS_DELIVERY_FAILED,
        ],
    ];

    public function createFromCart(int $userId, array $data): array
    {
        $latitude = $data['delivery_latitude'] ?? null;
        $longitude = $data['delivery_longitude'] ?? null;
        $latitude = $latitude === '' ? null : $latitude;
        $longitude = $longitude === '' ? null : $longitude;
        if (($latitude === null) !== ($longitude === null)
            || ($latitude !== null && (! is_numeric($latitude) || ! is_numeric($longitude)
                || ! is_finite((float) $latitude) || ! is_finite((float) $longitude)
                || abs((float) $latitude) > 90 || abs((float) $longitude) > 180))) {
            throw new InvalidArgumentException('Choose a valid delivery point.');
        }
        $cart = new CartService();
        $cartItems = $cart->items();

        if ($cartItems === []) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $db = db_connect();
        if (!$db->transBegin()) throw new \RuntimeException('Unable to begin checkout.');
        try {
            // The singleton lock serializes acceptance/capacity checks with settings updates and other checkouts.
            $settings = (new OperationalSettingsService())->get(true);
            $customer = $db->query('SELECT id FROM ' . $db->protectIdentifiers('users', true) . " WHERE id = ? AND role = 'customer' AND status = 'active' FOR UPDATE", [$userId])->getRowArray();
            if (!$customer) throw new InvalidArgumentException('An active customer account is required.');
            $query = $db->table('products')->whereIn('uid', array_column($cartItems, 'product_uid'))->orderBy('id', 'ASC')->getCompiledSelect();
            $lockedProducts = [];
            foreach ($db->query($query . ' FOR UPDATE')->getResultArray() as $product) $lockedProducts[$product['uid']] = $product;
            $pricing = (new PricingService())->calculateCart($cartItems, $lockedProducts);

            if ($pricing['lines'] === [] || count($pricing['lines']) !== count($cartItems)) {
                throw new InvalidArgumentException('Cart has no available products.');
            }

            $code = (string)(session('checkout_offer') ?? '');
            $quote = (new CheckoutSummaryService())->quote($pricing, $code, true, $settings);
            if (!$quote['can_place_order']) throw new OrderConflictException($quote['checkout_notice'] ?? 'This order cannot be accepted.');
            if (($code !== '' || isset($data['quote_token'])) && (!is_string($data['quote_token'] ?? null)
                || !hash_equals($quote['quote_token'], $data['quote_token']))) throw new OrderConflictException('Your cart, coupon or checkout rules changed. Review the current checkout total before placing the order.');
            $subtotal = $quote['subtotal']; $discountAmount = $quote['discount_amount'];
            $deliveryCharge = $quote['delivery_charge']; $totalAmount = $quote['total_amount'];

            $orders = new OrderModel();
            $orderItems = new OrderItemModel();
            $history = new OrderStatusHistoryModel();
            $products = new ProductModel();

            foreach ($pricing['lines'] as $line) {
                $product = $line['product'];
                $quantity = (int) $line['quantity'];

                if (! $product || ! (bool) $product['is_active']) {
                    throw new InvalidArgumentException('One or more products are no longer available.');
                }

                if ($quantity > (int) $product['stock_quantity']) {
                    throw new InvalidArgumentException("Not enough stock for {$product['name']}.");
                }
            }

            foreach ($pricing['lines'] as $line) {
                $product = $line['product'];
                $quantity = (int) $line['quantity'];
                $stockUpdated = $products
                    ->skipValidation(true)
                    ->update((int) $product['id'], [
                        'stock_quantity' => (int) $product['stock_quantity'] - $quantity,
                    ]);
                if (!$stockUpdated) throw new \RuntimeException('Unable to update product stock.');
            }

            $orderId = $orders
                ->skipValidation(true)
                ->insert([
                    'order_number'    => $this->generateOrderNumber(),
                    'user_id'         => $userId,
                    'subtotal'        => $subtotal,
                    'discount_amount' => $discountAmount,
                    'delivery_charge' => $deliveryCharge,
                    'total_amount'    => $totalAmount,
                    'payment_method'  => 'cod',
                    'payment_status'  => 'pending',
                    'order_status'    => self::STATUS_PENDING,
                    'customer_name'   => trim((string) $data['customer_name']),
                    'customer_phone'  => trim((string) $data['customer_phone']),
                    'address_line'    => trim((string) $data['address_line']),
                    'city'            => trim((string) $data['city']),
                    'state'           => trim((string) ($data['state'] ?? '')),
                    'postal_code'     => trim((string) $data['postal_code']),
                    'delivery_latitude' => $latitude === null ? null : (float) $latitude,
                    'delivery_longitude' => $longitude === null ? null : (float) $longitude,
                    'notes'           => trim((string) ($data['notes'] ?? '')) ?: null,
                ]);

            if (! $orderId) {
                throw new InvalidArgumentException('Unable to place order.');
            }
            if ($quote['offer'] !== null) (new OfferService())->record((int)$orderId, $quote['offer']);

            foreach ($pricing['lines'] as $line) {
                $itemId = $orderItems
                    ->skipValidation(true)
                    ->insert([
                        'order_id'     => $orderId,
                        'product_id'   => (int) $line['product']['id'],
                        'product_name' => $line['product']['name'],
                        'unit'         => $line['product']['unit'],
                        'quantity'     => (int) $line['quantity'],
                        'unit_price'   => (float) $line['unit_price'],
                        'total_price'  => (float) $line['total'],
                        'created_at'   => date('Y-m-d H:i:s'),
                    ]);

                if (! $itemId) {
                    throw new InvalidArgumentException('Unable to create order items.');
                }
            }

            $historyId = $history
                ->skipValidation(true)
                ->insert([
                    'order_id'   => $orderId,
                    'old_status' => null,
                    'new_status' => self::STATUS_PENDING,
                    'changed_by' => $userId,
                    'notes'      => 'Order placed by customer.',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

            if (! $historyId) {
                throw new InvalidArgumentException('Unable to create order history.');
            }

            if (!$db->transStatus() || !$db->transCommit()) {
                throw new InvalidArgumentException('Unable to place order.');
            }
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        $cart->clear();
        session()->remove('checkout_offer');

        return $orders->find((int) $orderId);
    }

    public function changeStatus(int $orderId, string $newStatus, ?int $changedBy = null, ?string $notes = null, ?string $expectedStatus = null): bool
    {
        $orders = new OrderModel();
        $history = new OrderStatusHistoryModel();
        $order = $orders->find($orderId);

        if (! $order) {
            throw new InvalidArgumentException('Order not found.');
        }

        $oldStatus = $order['order_status'];

        if ($newStatus === self::STATUS_DELIVERED) {
            $proof = (new \App\Models\DeliveryCompletionModel())->find($orderId);
            if (!$proof || (int) $proof['delivery_user_id'] !== $changedBy || empty($proof['verified_at'])) {
                throw new InvalidArgumentException('Complete this delivery through PIN verification and cash confirmation.');
            }
        }
        if ($newStatus === self::STATUS_DELIVERY_FAILED && trim((string) $notes) === '') {
            throw new InvalidArgumentException('Provide a reason for the failed delivery.');
        }

        if ($expectedStatus !== null && $oldStatus !== $expectedStatus) {
            throw new OrderConflictException('This order changed. Refresh its details before trying again.');
        }

        if (! $this->canTransition($oldStatus, $newStatus)) {
            throw new InvalidArgumentException("Order cannot move from {$oldStatus} to {$newStatus}.");
        }

        $db = db_connect();
        if (! $db->transBegin()) {
            throw new \RuntimeException('Unable to begin order update.');
        }
        try {
            // Compare-and-set prevents competing requests applying the same old transition.
            $updated = $db->table('orders')->where('id', $orderId)->where('order_status', $oldStatus)
                ->update(['order_status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]);
            if (! $updated) {
                throw new \RuntimeException('Unable to update order.');
            }
            if ($db->affectedRows() !== 1) {
                throw new OrderConflictException('This order changed. Refresh its details before trying again.');
            }
            $historyId = $history->insert([
                'order_id'   => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
                'notes'      => $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $historyId || ! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Unable to save order history.');
            }
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return true;
    }

    public function canTransition(string $oldStatus, string $newStatus): bool
    {
        return in_array($newStatus, self::ALLOWED_TRANSITIONS[$oldStatus] ?? [], true);
    }

    private function generateOrderNumber(): string
    {
        $orders = new OrderModel();

        do {
            $orderNumber = 'SW' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        } while ($orders->where('order_number', $orderNumber)->first() !== null);

        return $orderNumber;
    }
}
