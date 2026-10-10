<?php

namespace App\Models;

class DeliveryOrderModel extends OrderModel
{
    public function currentForRider(int $riderId, ?string $uid = null, bool $activeOnly = false): array
    {
        $query = $this->db->table('orders o')->select('o.*')->join('delivery_assignments a', 'a.order_id = o.id')
            ->where('a.delivery_user_id', $riderId)->where('NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id)', null, false);
        if ($uid !== null) $query->where('o.uid', $uid);
        if ($activeOnly) $query->whereIn('o.order_status', ['ready_for_delivery', 'out_for_delivery']);
        return $query->orderBy('o.created_at', 'DESC')->orderBy('o.id', 'DESC')->get()->getResultArray();
    }
}
