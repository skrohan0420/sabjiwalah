<?php

namespace App\Models;

/** Read operations for the admin order workspace. */
class AdminOrderModel extends OrderModel
{
    public const QUEUES = ['new' => ['pending'], 'preparing' => ['confirmed', 'preparing'], 'ready' => ['ready_for_delivery'], 'on_way' => ['out_for_delivery'], 'history' => ['delivered', 'cancelled', 'delivery_failed']];
    public function listing(array $filters, ?string $start, ?string $end): array
    {
        $query = $this->db->table('orders');
        if (isset(self::QUEUES[$filters['queue'] ?? ''])) $query->whereIn('order_status', self::QUEUES[$filters['queue']]);
        if (! empty($filters['status'])) {
            $query->where('order_status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->groupStart()->like('order_number', $filters['search'])->orLike('customer_name', $filters['search'])->groupEnd();
        }
        if ($start !== null) $query->where('created_at >=', $start);
        if ($end !== null) $query->where('created_at <', $end);
        $total = $query->countAllResults(false);
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $sort = in_array($filters['sort'] ?? '', ['created_at', 'order_number', 'total_amount'], true) ? $filters['sort'] : 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $rows = $query->orderBy($sort, $dir)->orderBy('id', $dir)->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $ids = array_column($rows, 'id');
        $counts = [];
        $assigned = [];
        if ($ids !== []) {
            foreach ($this->db->table('order_items')->select('order_id, COUNT(*) AS item_count')->whereIn('order_id', $ids)->groupBy('order_id')->get()->getResultArray() as $count) {
                $counts[$count['order_id']] = (int) $count['item_count'];
            }
            foreach ($this->db->table('delivery_assignments a')->select('a.order_id, u.name')
                ->join('users u', 'u.id = a.delivery_user_id', 'left')->whereIn('a.order_id', $ids)
                ->where('NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id)', null, false)
                ->orderBy('a.assigned_at', 'DESC')->orderBy('a.id', 'DESC')->get()->getResultArray() as $assignment) {
                $assigned[$assignment['order_id']] ??= $assignment['name'];
            }
        }
        foreach ($rows as &$order) {
            $order['item_count'] = $counts[$order['id']] ?? 0;
            $order['assigned_delivery_name'] = $assigned[$order['id']] ?? null;
        }
        unset($order);
        return ['items' => $rows, 'pager' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => (int) ceil($total / $perPage)]];
    }

    public function items(int $orderId): array
    {
        return $this->db->table('order_items')->select('uid, product_name, unit, quantity, unit_price, total_price')
            ->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function queueCounts(array $filters, ?string $start, ?string $end): array
    {
        $query = $this->db->table('orders')->select('order_status, COUNT(*) AS total')->groupBy('order_status');
        if (!empty($filters['search'])) $query->groupStart()->like('order_number', $filters['search'])->orLike('customer_name', $filters['search'])->groupEnd();
        if ($start !== null) $query->where('created_at >=', $start);
        if ($end !== null) $query->where('created_at <', $end);
        $byStatus = array_column($query->get()->getResultArray(), 'total', 'order_status');
        $counts = [];
        foreach (self::QUEUES as $key => $statuses) $counts[$key] = array_sum(array_map(static fn($status) => (int) ($byStatus[$status] ?? 0), $statuses));
        return $counts;
    }

    public function newest(): ?array
    {
        return $this->db->table('orders')->select('uid, order_number')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
    }

    public function history(int $orderId): array
    {
        return $this->db->table('order_status_history h')->select('h.uid, h.old_status, h.new_status, h.notes, h.created_at, u.name AS changed_by_name')
            ->join('users u', 'u.id = h.changed_by', 'left')->where('h.order_id', $orderId)->orderBy('h.created_at', 'ASC')->orderBy('h.id', 'ASC')->get()->getResultArray();
    }

    public function assignments(int $orderId): array
    {
        return $this->db->table('delivery_assignments a')->select('a.uid, a.assigned_at, a.completed_at, u.name AS delivery_name, manager.name AS assigned_by_name')
            ->join('users u', 'u.id = a.delivery_user_id', 'left')->join('users manager', 'manager.id = a.assigned_by', 'left')
            ->where('a.order_id', $orderId)->orderBy('a.id', 'DESC')->get()->getResultArray();
    }
}
