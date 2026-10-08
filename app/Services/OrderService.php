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

    private const DELIVERY_CHARGE = 40.00;
    private const FREE_DELIVERY_MINIMUM = 499.00;

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

        $pricing = (new PricingService())->calculateCart($cartItems);

        if ($pricing['lines'] === []) {
            throw new InvalidArgumentException('Cart has no available products.');
        }

        $subtotal = (float) $pricing['subtotal'];
        $discountAmount = 0.00;
        $deliveryCharge = $subtotal >= self::FREE_DELIVERY_MINIMUM ? 0.00 : self::DELIVERY_CHARGE;
        $totalAmount = $subtotal - $discountAmount + $deliveryCharge;

        $orders = new OrderModel();
        $orderItems = new OrderItemModel();
        $history = new OrderStatusHistoryModel();
        $products = new ProductModel();

        foreach ($pricing['lines'] as $line) {
            $product = $products->find((int) $line['product']['id']);
            $quantity = (int) $line['quantity'];

            if (! $product || ! (bool) $product['is_active']) {
                throw new InvalidArgumentException('One or more products are no longer available.');
            }

            if ($quantity > (int) $product['stock_quantity']) {
                throw new InvalidArgumentException("Not enough stock for {$product['name']}.");
            }
        }

        $db = db_connect();
        $db->transStart();

        foreach ($pricing['lines'] as $line) {
            $product = $products->find((int) $line['product']['id']);
            $quantity = (int) $line['quantity'];
            $products
                ->skipValidation(true)
                ->update((int) $product['id'], [
                    'stock_quantity' => (int) $product['stock_quantity'] - $quantity,
                ]);
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
            $db->transRollback();
            throw new InvalidArgumentException('Unable to place order.');
        }

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
                $db->transRollback();
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
            $db->transRollback();
            throw new InvalidArgumentException('Unable to create order history.');
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new InvalidArgumentException('Unable to place order.');
        }

        $cart->clear();

        return $orders->find((int) $orderId);
    }

    public function changeStatus(int $orderId, string $newStatus, ?int $changedBy = null, ?string $notes = null): bool
    {
        $orders = new OrderModel();
        $history = new OrderStatusHistoryModel();
        $order = $orders->find($orderId);

        if (! $order) {
            throw new InvalidArgumentException('Order not found.');
        }

        $oldStatus = $order['order_status'];

        if (! $this->canTransition($oldStatus, $newStatus)) {
            throw new InvalidArgumentException("Order cannot move from {$oldStatus} to {$newStatus}.");
        }

        $db = db_connect();
        $db->transStart();
        $orders->update($orderId, ['order_status' => $newStatus]);
        $history->insert([
            'order_id'   => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'notes'      => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->transComplete();

        return $db->transStatus();
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
