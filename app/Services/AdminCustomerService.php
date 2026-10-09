<?php

namespace App\Services;

use App\Models\AdminCustomerModel;

class AdminCustomerService
{
    public function __construct(private ?AdminCustomerModel $customers = null) { $this->customers ??= new AdminCustomerModel(); }

    public function listing(array $filters): array
    {
        $data = $this->customers->listing($filters);
        $data['items'] = array_map(fn (array $row): array => [
            'uid' => $row['uid'], 'name' => $row['name'], 'phone_masked' => $this->mask($row['phone']),
            'status' => $row['status'], 'registered_at' => $this->timestamp($row['created_at']), 'order_count' => $row['order_count'],
        ], $data['items']);
        return $data;
    }

    public function details(string $uid, int $page, int $perPage): ?array
    {
        $customer = $this->customers->customer($uid);
        if (! $customer) return null;
        $orders = $this->customers->history((int) $customer['id'], $page, $perPage);
        foreach ($orders['items'] as &$order) {
            $order['total_amount'] = (float) $order['total_amount'];
            $order['placed_at'] = $this->timestamp($order['created_at']);
            unset($order['created_at']);
        }
        unset($order);
        return ['customer' => [
            'uid' => $customer['uid'], 'name' => $customer['name'], 'email' => $customer['email'], 'phone' => $customer['phone'],
            'status' => $customer['status'], 'registered_at' => $this->timestamp($customer['created_at']), 'order_count' => $orders['pager']['total'],
        ], 'orders' => $orders];
    }

    public function changeStatus(string $uid, string $status, string $expected): string
    {
        $customer = $this->customers->customer($uid);
        if (! $customer) return 'missing';
        if ($customer['status'] !== $expected) return 'conflict';
        if ($status === $expected) return 'unchanged';
        return $this->customers->changeStatus((int) $customer['id'], $status, $expected) ? 'changed' : 'conflict';
    }

    private function mask(?string $phone): ?string
    {
        if (! $phone) return null;
        return strlen($phone) > 4 ? str_repeat('•', strlen($phone) - 4) . substr($phone, -4) : '••••';
    }

    private function timestamp(?string $value): ?string
    {
        return $value ? (new \DateTimeImmutable($value, new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
    }
}
