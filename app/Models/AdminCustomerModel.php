<?php

namespace App\Models;

class AdminCustomerModel extends UserModel
{
    public function listing(array $filters): array
    {
        $query = $this->db->table('users')->where('role', 'customer');
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (($filters['search'] ?? '') !== '') {
            $search = trim($filters['search']);
            $query->groupStart()->like('name', $search)->orLike('phone', $search);
            $digits = preg_replace('/\D+/', '', $search);
            if (strlen($digits) > 10) $digits = substr($digits, -10);
            if (strlen($digits) >= 4 && $digits !== $search) $query->orLike('phone', $digits);
            $query->groupEnd();
        }
        $total = $query->countAllResults(false);
        $page = (int) $filters['page']; $perPage = (int) $filters['per_page'];
        $sort = ($filters['sort'] ?? '') === 'name' ? 'name' : 'created_at';
        $dir = ($filters['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $rows = $query->select('id, uid, name, phone, status, created_at')->orderBy($sort, $dir)->orderBy('id', $dir)
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $counts = [];
        if ($rows !== []) {
            foreach ($this->db->table('orders')->select('user_id, COUNT(*) AS order_count')->whereIn('user_id', array_column($rows, 'id'))->groupBy('user_id')->get()->getResultArray() as $row) {
                $counts[$row['user_id']] = (int) $row['order_count'];
            }
        }
        foreach ($rows as &$row) $row['order_count'] = $counts[$row['id']] ?? 0;
        unset($row);
        return ['items' => $rows, 'pager' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => (int) ceil($total / $perPage)]];
    }

    public function customer(string $uid): ?array
    {
        return $this->select('id, uid, name, email, phone, status, created_at')->where('role', 'customer')->where('uid', $uid)->first();
    }

    public function history(int $id, int $page, int $perPage): array
    {
        $query = $this->db->table('orders')->where('user_id', $id);
        $total = $query->countAllResults(false);
        $items = $query->select('uid, order_number, total_amount, payment_status, order_status, created_at')
            ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['items' => $items, 'pager' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => (int) ceil($total / $perPage)]];
    }

    public function changeStatus(int $id, string $status, string $expected): bool
    {
        $success = $this->db->table('users')->where('id', $id)->where('role', 'customer')->where('status', $expected)
            ->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        if (! $success) throw new \RuntimeException('Customer status update failed');
        return $this->db->affectedRows() === 1;
    }
}
