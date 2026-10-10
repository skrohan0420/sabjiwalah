<?php

namespace App\Models;

use CodeIgniter\Model;

class DispatchModel extends Model
{
    private function queueQuery(array $filters): \CodeIgniter\Database\BaseBuilder
    {
        $query = $this->db->table('orders o')->join('delivery_assignments a', 'a.order_id = o.id AND NOT EXISTS (SELECT 1 FROM delivery_assignments newer WHERE newer.order_id = a.order_id AND newer.id > a.id)', 'left', false)
            ->join('users u', 'u.id = a.delivery_user_id', 'left');
        $query->whereIn('o.order_status', empty($filters['status']) ? ['ready_for_delivery', 'out_for_delivery'] : [$filters['status']]);
        if (($filters['search'] ?? '') !== '') $query->like('o.order_number', $filters['search']);
        if (($filters['assignment'] ?? '') === 'unassigned') $query->where('a.id', null);
        if (($filters['assignment'] ?? '') === 'assigned') $query->where('a.id IS NOT NULL', null, false);
        return $query;
    }

    public function queue(array $filters): array
    {
        $query = $this->queueQuery($filters); $total = $query->countAllResults(false);
        $items = $query->select('o.uid, o.order_number, o.order_status, o.updated_at, a.uid AS assignment_uid, a.assigned_at, u.uid AS rider_uid, u.name AS rider_name, u.status AS rider_status')
            ->orderBy('o.updated_at', 'ASC')->orderBy('o.id', 'ASC')->limit($filters['per_page'], ($filters['page'] - 1) * $filters['per_page'])->get()->getResultArray();
        return ['items' => $items, 'pager' => $this->pager($filters['page'], $filters['per_page'], $total)];
    }

    public function candidates(array $filters): array
    {
        $query = $this->db->table('users u')->select('u.uid, u.name')->join('delivery_personnel_profiles p', 'p.user_id = u.id')
            ->where('u.role', 'delivery')->where('u.status', 'active')->where('p.availability', 'available')
            ->where("NOT EXISTS (SELECT 1 FROM delivery_assignments a JOIN orders o ON o.id = a.order_id WHERE a.delivery_user_id = u.id AND o.order_status NOT IN ('delivered','cancelled','delivery_failed') AND NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id))", null, false);
        if (($filters['search'] ?? '') !== '') $query->like('u.name', $filters['search']);
        $total = $query->countAllResults(false);
        $items = $query->orderBy('u.name', 'ASC')->orderBy('u.id', 'ASC')->limit($filters['per_page'], ($filters['page'] - 1) * $filters['per_page'])->get()->getResultArray();
        return ['items' => $items, 'pager' => $this->pager($filters['page'], $filters['per_page'], $total)];
    }

    public function lockOrder(string $uid): ?array
    {
        return $this->locked('orders', 'uid', $uid);
    }

    private function locked(string $table, string $field, string|int $value): ?array
    {
        $sql = $this->db->table($table)->where($field, $value)->getCompiledSelect();
        $result = $this->db->query($sql . ' FOR UPDATE');
        if ($result === false) throw new \RuntimeException('Unable to lock dispatch record.');
        return $result->getRowArray();
    }

    public function lockUser(string|int $value, bool $byId = false): ?array { return $this->locked('users', $byId ? 'id' : 'uid', $value); }
    public function latest(int $orderId): ?array {
        // A current read avoids establishing a repeatable-read snapshot before the rider capacity lock.
        $sql = $this->db->table('delivery_assignments')->where('order_id', $orderId)->orderBy('id', 'DESC')->limit(1)->getCompiledSelect();
        $result = $this->db->query($sql . ' FOR UPDATE');
        if ($result === false) throw new \RuntimeException('Unable to read current assignment.');
        return $result->getRowArray();
    }
    public function availability(int $id): string { return $this->db->table('delivery_personnel_profiles')->where('user_id', $id)->get()->getRowArray()['availability'] ?? 'offline'; }
    public function workload(int $id): int
    {
        return $this->db->table('delivery_assignments a')->join('orders o', 'o.id = a.order_id')->where('a.delivery_user_id', $id)
            ->whereNotIn('o.order_status', ['delivered', 'cancelled', 'delivery_failed'])
            ->where('NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id)', null, false)->countAllResults();
    }
    private function pager(int $page, int $perPage, int $total): array { return ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => (int) ceil($total / $perPage)]; }
}
