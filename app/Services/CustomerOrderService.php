<?php

namespace App\Services;

class CustomerOrderService
{
    public function detail(string $uid, int $customerId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $uid)) throw new \OutOfBoundsException('Order not found.');
        $db = db_connect();
        // Scope the first query to an active customer; every subsequent read uses this authorized ID.
        $order = $db->table('orders o')->join('users c', 'c.id = o.user_id')
            ->select('o.id, o.uid, o.order_number, o.order_status, o.payment_method, o.payment_status, o.total_amount, o.address_line, o.city, o.postal_code, o.created_at, o.updated_at')
            ->where('o.uid', $uid)->where('o.user_id', $customerId)->where('c.role', 'customer')->where('c.status', 'active')->get()->getRowArray();
        if (!$order) throw new \OutOfBoundsException('Order not found.');
        $history = $db->table('order_status_history')->select('new_status, created_at')->where('order_id', $order['id'])->orderBy('id', 'ASC')->get()->getResultArray();
        $items = $db->table('order_items')->select('product_name, unit, quantity, unit_price, total_price')->where('order_id', $order['id'])->orderBy('id', 'ASC')->get()->getResultArray();
        $partner = null;
        if ($order['order_status'] === 'out_for_delivery') {
            $assigned = $db->table('delivery_assignments a')->join('users u', 'u.id = a.delivery_user_id')
                ->select('u.name, u.role, u.status')->where('a.order_id', $order['id'])->orderBy('a.id', 'DESC')->limit(1)->get()->getRowArray();
            if ($assigned && $assigned['role'] === 'delivery' && $assigned['status'] === 'active') $partner = ['name' => $assigned['name']];
        }
        $time = static fn(?string $value): ?string => $value ? (new \DateTimeImmutable($value, new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
        $labels = ['pending'=>'Awaiting confirmation','confirmed'=>'Order confirmed','preparing'=>'Preparing','ready_for_delivery'=>'Ready for delivery','out_for_delivery'=>'Out for delivery','delivered'=>'Delivered','cancelled'=>'Cancelled','delivery_failed'=>'Delivery unsuccessful'];
        $steps = ['confirmed','preparing','ready_for_delivery','out_for_delivery','delivered'];
        $current = array_search($order['order_status'], $steps, true);
        $stamps = [];
        foreach ($history as $entry) if (isset($labels[$entry['new_status']])) $stamps[$entry['new_status']] = $time($entry['created_at']);
        $progress = [];
        foreach ($steps as $index=>$status) {
            // History records actual events. Skipped states never receive invented timestamps.
            $state = $order['order_status'] === $status ? 'current' : (isset($stamps[$status]) || ($current !== false && $index < $current) ? 'complete' : 'upcoming');
            $progress[] = ['status'=>$status,'label'=>$labels[$status],'state'=>$state,'recorded_at'=>$stamps[$status] ?? null];
        }
        unset($order['id']);
        $order['placed_at']=$time($order['created_at']); $order['updated_at']=$time($order['updated_at']); unset($order['created_at']);
        $order['status_label']=$labels[$order['order_status']] ?? 'Status unavailable';
        return ['order'=>$order,'items'=>$items,'progress'=>$progress,'delivery_partner'=>$partner,'estimated_delivery_at'=>null,'latest_update'=>['status'=>$order['order_status'],'label'=>$order['status_label'],'recorded_at'=>$stamps[$order['order_status']] ?? $order['updated_at']],'terminal'=>in_array($order['order_status'], ['delivered','cancelled','delivery_failed'], true)];
    }
}
