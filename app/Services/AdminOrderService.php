<?php

namespace App\Services;

use App\Models\AdminOrderModel;
use DateTimeImmutable;
use DateTimeZone;

class AdminOrderService
{
    public function __construct(private ?AdminOrderModel $orders = null)
    {
        $this->orders ??= new AdminOrderModel();
    }

    public function listing(array $filters): array
    {
        $data = $this->orders->listing($filters, $this->dateBoundary($filters['from'] ?? null), $this->dateBoundary($filters['to'] ?? null, true));
        $latest = $this->orders->newest();
        $data['latest_order'] = $latest ? ['uid' => $latest['uid'], 'order_number' => $latest['order_number']] : null;
        return $data;
    }

    public function details(string $uid): ?array
    {
        $order = $this->orders->findByUid($uid);
        if (! $order) return null;
        $id = (int) $order['id'];
        $items = $this->orders->items($id);
        foreach ($items as &$item) {
            $item['quantity'] = (int) $item['quantity'];
            $item['unit_price'] = (float) $item['unit_price'];
            $item['total_price'] = (float) $item['total_price'];
        }
        unset($item);
        $history = $this->orders->history($id);
        foreach ($history as &$entry) $entry['occurred_at'] = $this->timestamp($entry['created_at']);
        unset($entry);
        $assignments = $this->orders->assignments($id);
        foreach ($assignments as &$assignment) {
            $assignment['assigned_time'] = $this->timestamp($assignment['assigned_at']);
            $assignment['completed_time'] = $this->timestamp($assignment['completed_at']);
        }
        unset($assignment);
        // Phase 3 exposes preparation/cancellation only; dispatch and completion remain later modules.
        $actions = array_values(array_intersect(OrderService::ALLOWED_TRANSITIONS[$order['order_status']] ?? [], ['confirmed', 'preparing', 'ready_for_delivery', 'cancelled']));
        return ['order' => $order, 'items' => $items, 'history' => $history, 'assignments' => $assignments, 'allowed_actions' => $actions];
    }

    public function timestamp(?string $date): ?string
    {
        return $date ? (new DateTimeImmutable($date, new DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
    }

    private function dateBoundary(?string $date, bool $nextDay = false): ?string
    {
        if (! $date) return null;
        $time = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone(AdminDashboardService::BUSINESS_TIMEZONE));
        if ($nextDay) $time = $time->modify('+1 day');
        return $time->setTimezone(new DateTimeZone(config('App')->appTimezone))->format('Y-m-d H:i:s');
    }
}
