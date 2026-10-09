<?php

namespace App\Models;

use CodeIgniter\Model;

/** Read-only, bounded dashboard queries; no per-order lookups. */
class AdminDashboardModel extends Model
{
    protected $table = 'orders';
    protected $returnType = 'array';

    public function overview(string $start, string $end, int $lowStock): array
    {
        $orders = $this->db->table('orders');
        $orders->select('COUNT(*) AS total_orders');
        foreach (['pending', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled', 'delivery_failed'] as $status) {
            $orders->select("COALESCE(SUM(CASE WHEN order_status = '{$status}' THEN 1 ELSE 0 END), 0) AS {$status}", false);
        }
        $start = $this->db->escape($start);
        $end = $this->db->escape($end);
        $today = "created_at >= {$start} AND created_at < {$end}";
        $orders->select("COALESCE(SUM(CASE WHEN {$today} THEN 1 ELSE 0 END), 0) AS orders_today", false);
        $orders->select("COALESCE(SUM(CASE WHEN {$today} AND order_status = 'delivered' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS sales_today", false);
        $totals = $orders->get()->getRowArray();
        $products = $this->db->table('products')->select('COUNT(*) AS total_products')
            ->select('COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_products', false)
            ->select('COALESCE(SUM(CASE WHEN is_active = 1 AND stock_quantity <= ' . $lowStock . ' THEN 1 ELSE 0 END), 0) AS low_stock_products', false)
            ->get()->getRowArray();

        return [
            'totals' => array_merge($totals, $products, [
                'total_customers' => $this->db->table('users')->where('role', 'customer')->countAllResults(),
            ]),
            'recent_orders' => $this->orderRows(false),
            'attention_orders' => $this->orderRows(true),
        ];
    }

    private function orderRows(bool $attention): array
    {
        $query = $this->db->table('orders')->select('uid, order_number, customer_name, total_amount, order_status, payment_status, created_at');
        if ($attention) {
            $query->whereIn('order_status', ['pending', 'ready_for_delivery', 'delivery_failed'])->orderBy('created_at', 'ASC');
        } else {
            $query->orderBy('created_at', 'DESC');
        }
        return $query->orderBy('id', $attention ? 'ASC' : 'DESC')->limit(8)->get()->getResultArray();
    }
}
