<?php

namespace App\Services;

use App\Models\DeliveryAssignmentModel;
use App\Models\DispatchModel;

class DispatchService
{
    public function queue(array $filters): array
    {
        $data = (new DispatchModel())->queue($filters);
        foreach ($data['items'] as &$order) {
            $changed = new \DateTimeImmutable($order['updated_at'], new \DateTimeZone(config('App')->appTimezone));
            $order['waiting_minutes'] = max(0, (int) floor((time() - $changed->getTimestamp()) / 60));
            $order['delayed'] = $order['waiting_minutes'] >= ($order['order_status'] === 'ready_for_delivery' ? 15 : 30);
            $order['status_changed_at'] = $changed->format(DATE_ATOM);
            $order['assigned_at'] = $order['assigned_at'] ? (new \DateTimeImmutable($order['assigned_at'], new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
            $order['destination'] = $this->coordinates($order['delivery_latitude'], $order['delivery_longitude']);
            $order['rider_location'] = null;
            $point = $this->coordinates($order['rider_latitude'], $order['rider_longitude']);
            if ($point && is_numeric($order['rider_accuracy']) && (float) $order['rider_accuracy'] >= 0 && (float) $order['rider_accuracy'] <= 100 && $order['location_recorded_at'] && $order['location_expires_at']) {
                $recorded = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $order['location_recorded_at'], new \DateTimeZone('UTC'));
                $expires = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $order['location_expires_at'], new \DateTimeZone('UTC'));
                $age = $recorded ? time() - $recorded->getTimestamp() : -1;
                if ($recorded && $expires && $age >= 0 && $expires->getTimestamp() > time()) $order['rider_location'] = $point + ['accuracy' => (float) $order['rider_accuracy'], 'recorded_at' => $recorded->format(DATE_ATOM), 'age_seconds' => $age, 'stale' => $age >= 120];
            }
            unset($order['delivery_latitude'], $order['delivery_longitude'], $order['rider_latitude'], $order['rider_longitude'], $order['rider_accuracy'], $order['location_recorded_at'], $order['location_expires_at']);
            unset($order['updated_at']);
        }
        unset($order);
        return $data;
    }

    private function coordinates(mixed $lat, mixed $lng): ?array
    {
        if (!is_numeric($lat) || !is_numeric($lng) || !is_finite((float) $lat) || !is_finite((float) $lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) return null;
        return ['latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    public function assign(string $orderUid, string $riderUid, string $expectedAssignment, int $actorId): array
    {
        $db = db_connect(); $model = new DispatchModel();
        if (! $db->transBegin()) throw new \RuntimeException('Unable to begin dispatch transaction');
        try {
            // All dispatch/status writers lock the order first. Rider locks serialize capacity checks across orders.
            $order = $model->lockOrder($orderUid);
            if (! $order) throw new \OutOfBoundsException('Order not found.');
            $actor = $model->lockUser($actorId, true);
            if (! $actor || $actor['role'] !== 'admin' || $actor['status'] !== 'active') throw new \DomainException('Admin access required.');
            if (!in_array($order['order_status'], [OrderService::STATUS_PREPARING, OrderService::STATUS_READY_FOR_DELIVERY], true)) throw new OrderConflictException('Only preparing or ready orders can be assigned or reassigned.');
            $latest = $model->latest((int) $order['id']);
            if (($latest['uid'] ?? 'none') !== $expectedAssignment) throw new OrderConflictException('Assignment changed. Reload dispatch before trying again.');
            $rider = $model->lockUser($riderUid);
            if (! $rider || $rider['role'] !== 'delivery') throw new \OutOfBoundsException('Delivery account not found.');
            if ($latest && (int) $latest['delivery_user_id'] === (int) $rider['id']) {
                $assignmentUid = $latest['uid'];
            } else {
                if ($rider['status'] !== 'active' || $model->availability((int) $rider['id']) !== 'available' || $model->workload((int) $rider['id']) > 0) throw new OrderConflictException('The delivery person is no longer available. Choose another person or reload.');
                $assignments = new DeliveryAssignmentModel();
                $id = $assignments->insert(['order_id' => $order['id'], 'delivery_user_id' => $rider['id'], 'assigned_by' => $actorId, 'assigned_at' => date('Y-m-d H:i:s')]);
                if (! $id) throw new \RuntimeException('Unable to save assignment');
                $assignmentUid = $assignments->find($id)['uid'];
                (new \App\Models\DeliveryLocationModel())->clearOrder((int) $order['id']);
            }
            if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Unable to commit assignment');
            return ['order_uid' => $orderUid, 'assignment_uid' => $assignmentUid, 'rider_uid' => $riderUid];
        } catch (\Throwable $exception) {
            // Concurrent first assignments can contend on InnoDB's empty-index range locks.
            // The losing transaction must be a reviewable conflict, with all writes rolled back.
            $error = $db->error();
            $db->transRollback();
            if (in_array((int) ($error['code'] ?? 0), [1205, 1213], true)) {
                throw new OrderConflictException('Dispatch is busy or changed. Reload before assigning again.');
            }
            throw $exception;
        }
    }
}
