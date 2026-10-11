<?php

namespace App\Services;

use App\Models\DispatchModel;

class DeliveryLocationService
{
    public const INTERVAL_SECONDS = 15;
    // Five-minute pruning cadence keeps physical GPS retention within one hour.
    public const RETENTION_SECONDS = 3300;

    public static function validSample(array $data, ?int $nowMs = null): bool
    {
        if (array_diff(array_keys($data), ['order_uid', 'assignment_uid', 'latitude', 'longitude', 'accuracy', 'captured_at_ms'])) return false;
        foreach (['order_uid', 'assignment_uid'] as $field) if (!isset($data[$field]) || !is_string($data[$field]) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $data[$field])) return false;
        foreach (['latitude', 'longitude', 'accuracy'] as $field) if (!isset($data[$field]) || (!is_int($data[$field]) && !is_float($data[$field])) || !is_finite((float) $data[$field])) return false;
        if (abs($data['latitude']) > 90 || abs($data['longitude']) > 180 || $data['accuracy'] < 0 || $data['accuracy'] > 100) return false;
        $nowMs ??= (int) floor(microtime(true) * 1000);
        return isset($data['captured_at_ms']) && is_int($data['captured_at_ms']) && $data['captured_at_ms'] >= $nowMs - 60000 && $data['captured_at_ms'] <= $nowMs + 10000;
    }

    public function record(int $riderId, array $data, ?int $nowMs = null): array
    {
        $nowMs ??= (int) floor(microtime(true) * 1000);
        if (!self::validSample($data, $nowMs)) throw new \InvalidArgumentException('Use a fresh location with accuracy within 100 metres.');
        $db = db_connect(); $model = new DispatchModel();
        if (!$db->transBegin()) throw new \RuntimeException('Unable to start location update.');
        try {
            // Same lock order as dispatch/completion: order, rider, latest assignment.
            $order = $model->lockOrder($data['order_uid']);
            $rider = $model->lockUser($riderId, true);
            $assignment = $order ? $model->latest((int) $order['id']) : null;
            if (!$order || !$rider || $rider['role'] !== 'delivery' || $rider['status'] !== 'active' || !$assignment || (int) $assignment['delivery_user_id'] !== $riderId) throw new \OutOfBoundsException('Order not found.');
            if ($assignment['uid'] !== $data['assignment_uid'] || $order['order_status'] !== 'out_for_delivery') throw new OrderConflictException('Tracking assignment changed or delivery is no longer active.');
            $sql = $db->table('delivery_locations')->where('delivery_user_id', $riderId)->getCompiledSelect();
            $previous = $db->query($sql . ' FOR UPDATE')->getRowArray();
            $duplicate = $previous && $previous['latitude'] !== null && (int) $previous['assignment_id'] === (int) $assignment['id'] && (int) $previous['captured_at_ms'] === $data['captured_at_ms'];
            if ($duplicate) {
                foreach (['latitude', 'longitude', 'accuracy'] as $field) if (abs((float) $previous[$field] - $data[$field]) > ($field === 'accuracy' ? 0.005 : 0.00000005)) throw new OrderConflictException('Conflicting duplicate location.');
            } else {
                if ($previous && $data['captured_at_ms'] <= (int) $previous['captured_at_ms']) throw new OrderConflictException('Location sample is older than the last accepted update.');
                if ($previous && (int) floor($nowMs / 1000) - (new \DateTimeImmutable($previous['recorded_at'], new \DateTimeZone('UTC')))->getTimestamp() < self::INTERVAL_SECONDS) throw new DeliveryLocationRateLimitException();
                $stamp = gmdate('Y-m-d H:i:s', (int) floor($nowMs / 1000));
                $row = ['delivery_user_id' => $riderId, 'order_id' => $order['id'], 'assignment_id' => $assignment['id'], 'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'accuracy' => $data['accuracy'], 'captured_at_ms' => $data['captured_at_ms'], 'recorded_at' => $stamp, 'expires_at' => gmdate('Y-m-d H:i:s', (int) floor($nowMs / 1000) + self::RETENTION_SECONDS)];
                $ok = $previous ? $db->table('delivery_locations')->where('delivery_user_id', $riderId)->update($row) : $db->table('delivery_locations')->insert($row);
                if (!$ok) throw new \RuntimeException('Unable to save location.');
            }
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Unable to commit location.');
            return ['duplicate' => (bool) $duplicate, 'recorded_at' => str_replace(' ', 'T', $duplicate ? $previous['recorded_at'] : $stamp) . 'Z'];
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }

    public function stop(int $riderId): void
    {
        $db = db_connect();
        // Serialize against an in-flight upload before removing the latest sample.
        if (!$db->transBegin()) throw new \RuntimeException('Unable to stop tracking.');
        try {
            (new DispatchModel())->lockUser($riderId, true);
            if (!$db->table('delivery_locations')->where('delivery_user_id', $riderId)->update(['latitude' => null, 'longitude' => null, 'accuracy' => null]) || !$db->transCommit()) throw new \RuntimeException('Unable to clear location.');
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }
}
