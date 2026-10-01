<?php

namespace App\Services;

use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
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
}
