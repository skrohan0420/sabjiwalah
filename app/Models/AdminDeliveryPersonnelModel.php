<?php

namespace App\Models;

class AdminDeliveryPersonnelModel extends UserModel
{
    // Only the newest assignment owns an order. Historical assignees retain history, not workload.
    private function assignments(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('delivery_assignments a')->join('orders o', 'o.id = a.order_id')
            ->join('delivery_assignments newer', 'newer.order_id = a.order_id AND newer.id > a.id', 'left');
    }

    public function listing(array $filters): array
    {
        $query = $this->db->table('users u')->where('u.role', 'delivery');
        if (! empty($filters['status'])) $query->where('u.status', $filters['status']);
        if (($filters['search'] ?? '') !== '') {
            $search = trim($filters['search']);
            $query->groupStart()->like('u.name', $search)->orLike('u.phone', $search);
            $digits = preg_replace('/\D+/', '', $search);
            if (strlen($digits) > 10) $digits = substr($digits, -10);
            if (strlen($digits) >= 4 && $digits !== $search) $query->orLike('u.phone', $digits);
            $query->groupEnd();
        }
        $total = $query->countAllResults(false);
        $page = (int) $filters['page']; $perPage = (int) $filters['per_page'];
        $rows = $query->select('u.id, u.uid, u.name, u.phone, u.status, u.created_at, p.availability')
            ->join('delivery_personnel_profiles p', 'p.user_id = u.id', 'left')
            ->orderBy('u.name', 'ASC')->orderBy('u.id', 'ASC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $counts = $this->counts(array_column($rows, 'id'));
        foreach ($rows as &$row) $row += $counts[$row['id']] ?? ['workload' => 0, 'out_for_delivery' => 0, 'completed_deliveries' => 0];
        unset($row);
        return ['items' => $rows, 'pager' => $this->pager($page, $perPage, $total)];
    }

    public function personnel(string $uid): ?array
    {
        return $this->db->table('users u')->select('u.id, u.uid, u.name, u.email, u.phone, u.status, u.created_at, p.availability')
            ->join('delivery_personnel_profiles p', 'p.user_id = u.id', 'left')->where('u.role', 'delivery')->where('u.uid', $uid)->get()->getRowArray();
    }

    public function counts(array $ids): array
    {
        if ($ids === []) return [];
        $rows = $this->assignments()->select("a.delivery_user_id,
            COUNT(DISTINCT CASE WHEN newer.id IS NULL AND o.order_status NOT IN ('delivered','cancelled','delivery_failed') THEN o.id END) AS workload,
            COUNT(DISTINCT CASE WHEN newer.id IS NULL AND o.order_status = 'out_for_delivery' THEN o.id END) AS out_for_delivery,
            COUNT(DISTINCT CASE WHEN a.completed_at IS NOT NULL OR (newer.id IS NULL AND o.order_status = 'delivered') THEN o.id END) AS completed_deliveries", false)
            ->whereIn('a.delivery_user_id', $ids)->groupBy('a.delivery_user_id')->get()->getResultArray();
        $counts = [];
        foreach ($rows as $row) $counts[$row['delivery_user_id']] = array_map('intval', array_diff_key($row, ['delivery_user_id' => true]));
        return $counts;
    }

    public function history(int $id, int $page, int $perPage, bool $current = false): array
    {
        // NOT EXISTS avoids duplicating history rows when an order has been reassigned more than once.
        $latest = 'NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id)';
        $query = $this->db->table('delivery_assignments a')->join('orders o', 'o.id = a.order_id')->where('a.delivery_user_id', $id);
        if ($current) $query->where($latest, null, false)->whereNotIn('o.order_status', ['delivered', 'cancelled', 'delivery_failed']);
        $total = $query->countAllResults(false);
        $rows = $query->select("a.uid AS assignment_uid, o.uid, o.order_number, o.order_status, a.assigned_at, a.completed_at, ($latest) AS is_current", false)
            ->orderBy('a.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['items' => $rows, 'pager' => $this->pager($page, $perPage, $total)];
    }

    public function createPersonnel(array $data): string
    {
        if (! $this->db->transBegin()) throw new \RuntimeException('Unable to begin account transaction');
        try {
            $id = $this->insert($data);
            if (! $id) throw new \RuntimeException('Account insert failed');
            if (! $this->db->table('delivery_personnel_profiles')->insert(['user_id' => $id, 'availability' => 'offline', 'updated_at' => date('Y-m-d H:i:s')])) throw new \RuntimeException('Profile insert failed');
            $uid = $this->find($id)['uid'];
            if (! $this->db->transStatus() || ! $this->db->transCommit()) throw new \RuntimeException('Account commit failed');
            return $uid;
        } catch (\Throwable $exception) { $this->db->transRollback(); throw $exception; }
    }

    public function change(string $uid, string $field, string $value, string $expected): string
    {
        if (! $this->db->transBegin()) throw new \RuntimeException('Unable to begin account transaction');
        try {
            $user = $this->db->query('SELECT id, status FROM users WHERE uid = ? AND role = ? FOR UPDATE', [$uid, 'delivery'])->getRowArray();
            $result = 'missing';
            if ($user) {
                $profile = $this->db->table('delivery_personnel_profiles')->where('user_id', $user['id'])->get()->getRowArray();
                $observed = $field === 'status' ? $user['status'] : ($profile['availability'] ?? 'offline');
                $result = $observed !== $expected ? 'conflict' : 'unchanged';
                if ($field === 'availability' && $user['status'] !== 'active') $result = 'inactive';
                if ($result === 'unchanged' && $value !== $observed) {
                    if ($field === 'status' && ! $this->db->table('users')->where('id', $user['id'])->update(['status' => $value, 'updated_at' => date('Y-m-d H:i:s')])) throw new \RuntimeException('Account update failed');
                    if ($field === 'availability' || $value === 'inactive') {
                        $payload = ['availability' => $field === 'availability' ? $value : 'offline', 'updated_at' => date('Y-m-d H:i:s')];
                        $builder = $this->db->table('delivery_personnel_profiles');
                        $success = $profile ? $builder->where('user_id', $user['id'])->update($payload) : $builder->insert(['user_id' => $user['id']] + $payload);
                        if (! $success) throw new \RuntimeException('Availability update failed');
                    }
                    $result = 'changed';
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) throw new \RuntimeException('Account update commit failed');
            return $result;
        } catch (\Throwable $exception) { $this->db->transRollback(); throw $exception; }
    }

    private function pager(int $page, int $perPage, int $total): array
    {
        return ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => (int) ceil($total / $perPage)];
    }
}
