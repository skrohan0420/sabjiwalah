<?php

namespace App\Services;

use App\Models\DeliveryCompletionModel;
use App\Models\DeliveryOrderModel;
use App\Models\UserModel;

class DeliveryDashboardService
{
    public function snapshot(int $riderId, ?\DateTimeImmutable $now = null): array
    {
        $db = db_connect();
        if (! $db->transBegin()) throw new \RuntimeException('Unable to read dashboard.');
        try {
            $rider = (new UserModel())->select('id, uid, name, role, status')->find($riderId);
            if (! $rider || $rider['role'] !== 'delivery' || $rider['status'] !== 'active') throw new \OutOfBoundsException('Active delivery account required.');
            $now = ($now ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Kolkata'));
            $zone = new \DateTimeZone(config('App')->appTimezone);
            $start = $now->setTime(0, 0)->setTimezone($zone)->format('Y-m-d H:i:s');
            $end = $now->setTime(0, 0)->modify('+1 day')->setTimezone($zone)->format('Y-m-d H:i:s');
            $summary = (new DeliveryOrderModel())->dashboardSummary($riderId);
            if ($summary['active_order']) $summary['active_order']['assigned_at'] = $this->timestamp($summary['active_order']['assigned_at'], $zone);
            foreach ($summary['recent_assignments'] as &$assignment) $assignment['assigned_at'] = $this->timestamp($assignment['assigned_at'], $zone);
            unset($assignment);
            $result = $summary + (new DeliveryCompletionModel())->riderToday($riderId, $start, $end) + [
                'rider_name' => $rider['name'],
                'generated_at' => $now->format(DATE_ATOM), 'day' => $now->format('Y-m-d'), 'timezone' => 'Asia/Kolkata',
            ] + (new AdminDeliveryPersonnelService())->availabilityStateFor($rider['uid']);
            if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Unable to read dashboard.');
            return $result;
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }

    private function timestamp(?string $value, \DateTimeZone $zone): ?string
    {
        return $value ? (new \DateTimeImmutable($value, $zone))->format(DATE_ATOM) : null;
    }
}
