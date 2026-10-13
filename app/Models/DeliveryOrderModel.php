<?php

namespace App\Models;

class DeliveryOrderModel extends OrderModel
{
    public function currentForRider(int $riderId, ?string $uid = null, bool $activeOnly = false): array
    {
        $query = $this->currentQuery($riderId)->select('o.*, a.uid AS assignment_uid, a.assigned_at, a.completed_at');
        if ($uid !== null) $query->where('o.uid', $uid);
        if ($activeOnly) $query->whereIn('o.order_status', ['preparing', 'ready_for_delivery', 'out_for_delivery']);
        return $query->orderBy('o.created_at', 'DESC')->orderBy('o.id', 'DESC')->get()->getResultArray();
    }

    private function currentQuery(int $riderId): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('orders o')->join('delivery_assignments a', 'a.order_id = o.id')
            ->where('a.delivery_user_id', $riderId)->where('NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id)', null, false);
    }

    public function dashboardSummary(int $riderId): array
    {
        $counts = $this->currentQuery($riderId)->whereIn('o.order_status', ['preparing', 'ready_for_delivery', 'out_for_delivery'])
            ->select("COUNT(*) AS active_count, COALESCE(SUM(o.order_status IN ('preparing','ready_for_delivery')),0) AS pending_pickups", false)->get()->getRowArray();
        $active = $this->currentQuery($riderId)->whereIn('o.order_status', ['preparing', 'ready_for_delivery', 'out_for_delivery'])
            ->select('o.uid, o.order_number, o.order_status, o.total_amount, o.payment_method, a.assigned_at')
            ->orderBy('o.order_status', 'ASC')->orderBy('a.assigned_at', 'ASC')->orderBy('a.id', 'ASC')->limit(1)->get()->getRowArray();
        $recent = $this->currentQuery($riderId)->select('a.uid AS assignment_uid, o.order_number, a.assigned_at')
            ->orderBy('a.id', 'DESC')->limit(5)->get()->getResultArray();
        return ['active_count' => (int) $counts['active_count'], 'pending_pickups' => (int) $counts['pending_pickups'], 'active_order' => $active, 'recent_assignments' => $recent];
    }
}
