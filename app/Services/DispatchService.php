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
            unset($order['updated_at']);
        }
        unset($order);
        return $data;
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
            if ($order['order_status'] !== OrderService::STATUS_READY_FOR_DELIVERY) throw new OrderConflictException('Only orders ready for delivery can be assigned or reassigned.');
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
