<?php

namespace App\Services;

use App\Models\DispatchModel;

class DeliveryOrderService
{
    public function changeStatus(string $uid, string $status, int $actorId, ?string $notes = null, ?string $expectedStatus = null): void
    {
        $db = db_connect(); $model = new DispatchModel();
        if (! $db->transBegin()) throw new \RuntimeException('Unable to begin delivery update');
        try {
            $order = $model->lockOrder($uid);
            $actor = $model->lockUser($actorId, true);
            $latest = $order ? $model->latest((int) $order['id']) : null;
            if (! $order || ! $latest || (int) $latest['delivery_user_id'] !== $actorId || ! $actor || $actor['role'] !== 'delivery' || $actor['status'] !== 'active') throw new \OutOfBoundsException('Order not found.');
            (new OrderService())->changeStatus((int) $order['id'], $status, $actorId, $notes, $expectedStatus);
            if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Unable to commit delivery update');
        } catch (\Throwable $exception) { $db->transRollback(); throw $exception; }
    }
}
