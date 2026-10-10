<?php

namespace App\Services;

use App\Models\DispatchModel;
use App\Models\OrderDeliveryPinModel;

class DeliveryPinService
{
    public const LIFETIME_SECONDS = 1800;
    public const REISSUE_SECONDS = 60;

    /** Plaintext is returned once to the active customer who owns the order. */
    public function issue(string $uid, int $customerId): array
    {
        $db = db_connect();
        $dispatch = new DispatchModel();
        $pins = new OrderDeliveryPinModel();
        if (! $db->transBegin()) throw new \RuntimeException('Unable to begin PIN issuance.');
        try {
            // Same lock order as dispatch/status updates; serializes first issuance and rotation.
            $order = $dispatch->lockOrder($uid);
            $customer = $dispatch->lockUser($customerId, true);
            if (! $order || (int) $order['user_id'] !== $customerId || ! $customer
                || $customer['role'] !== 'customer' || $customer['status'] !== 'active') {
                throw new \OutOfBoundsException('Order not found.');
            }
            if ($order['order_status'] !== OrderService::STATUS_OUT_FOR_DELIVERY) {
                throw new OrderConflictException('Delivery PINs are available only while your order is out for delivery.');
            }
            $existing = $pins->locked((int) $order['id']);
            $now = time();
            if ($existing && (int) $existing['failed_attempts'] >= 5 && !empty($existing['attempt_window_ends_at'])) {
                $windowEnd = new \DateTimeImmutable($existing['attempt_window_ends_at'], new \DateTimeZone(config('App')->appTimezone));
                if ($windowEnd->getTimestamp() > $now) throw new DeliveryPinCooldownException($windowEnd->getTimestamp() - $now);
            }
            if ($existing) {
                $issued = new \DateTimeImmutable($existing['issued_at'], new \DateTimeZone(config('App')->appTimezone));
                $remaining = $issued->getTimestamp() + self::REISSUE_SECONDS - $now;
                if ($remaining > 0) throw new DeliveryPinCooldownException($remaining);
            }
            do {
                $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while ($existing && password_verify($pin, $existing['pin_hash']));
            $zone = new \DateTimeZone(config('App')->appTimezone);
            $issued = (new \DateTimeImmutable('@' . $now))->setTimezone($zone);
            $expires = $issued->modify('+' . self::LIFETIME_SECONDS . ' seconds');
            $record = [
                'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
                'issued_at' => $issued->format('Y-m-d H:i:s'),
                'expires_at' => $expires->format('Y-m-d H:i:s'),
            ];
            $saved = $existing ? $pins->update((int) $order['id'], $record)
                : $pins->insert(['order_id' => (int) $order['id']] + $record);
            if ($saved === false || ! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Unable to save delivery PIN.');
            return ['pin' => $pin, 'expires_at' => $expires->format(DATE_ATOM),
                'valid_for_seconds' => self::LIFETIME_SECONDS, 'retry_after' => self::REISSUE_SECONDS];
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
}
