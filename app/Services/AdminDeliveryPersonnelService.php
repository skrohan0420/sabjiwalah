<?php

namespace App\Services;

use App\Models\AdminDeliveryPersonnelModel;

class AdminDeliveryPersonnelService
{
    public function __construct(private ?AdminDeliveryPersonnelModel $personnel = null) { $this->personnel ??= new AdminDeliveryPersonnelModel(); }

    public function listing(array $filters): array
    {
        $data = $this->personnel->listing($filters);
        $data['items'] = array_map(fn (array $row): array => $this->publicPersonnel($row), $data['items']);
        return $data;
    }

    public function details(string $uid, int $page, int $perPage, string $mode): ?array
    {
        $row = $this->personnel->personnel($uid);
        if (! $row) return null;
        $row += $this->personnel->counts([$row['id']])[$row['id']] ?? ['workload' => 0, 'out_for_delivery' => 0, 'completed_deliveries' => 0];
        $orders = $this->personnel->history((int) $row['id'], $page, $perPage, $mode === 'current');
        foreach ($orders['items'] as &$order) {
            $order['is_current'] = (bool) $order['is_current'];
            $order['assigned_at'] = $this->timestamp($order['assigned_at']);
            $order['completed_at'] = $this->timestamp($order['completed_at']);
        }
        unset($order);
        return ['personnel' => $this->publicPersonnel($row) + ['phone' => $row['phone'], 'email' => $row['email']], 'orders' => $orders];
    }

    public function create(array $data): string
    {
        return $this->personnel->createPersonnel([
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?: null,
            'role' => 'delivery', 'status' => 'inactive', 'password_hash' => null,
        ]);
    }

    public function change(string $uid, string $field, string $value, string $expected): string
    {
        return $this->personnel->change($uid, $field, $value, $expected);
    }

    private function publicPersonnel(array $row): array
    {
        $base = $row['availability'] ?? 'offline';
        $effective = $row['status'] !== 'active' ? 'unavailable' : ($row['out_for_delivery'] > 0 ? 'busy' : ($row['workload'] > 0 ? 'assigned' : $base));
        return [
            'uid' => $row['uid'], 'name' => $row['name'], 'phone_masked' => $row['phone'] ? (strlen($row['phone']) > 4 ? str_repeat('•', strlen($row['phone']) - 4) . substr($row['phone'], -4) : '••••') : null,
            'status' => $row['status'], 'registered_at' => $this->timestamp($row['created_at']), 'base_availability' => $base, 'availability' => $effective,
            'workload' => $row['workload'], 'out_for_delivery' => $row['out_for_delivery'], 'completed_deliveries' => $row['completed_deliveries'],
        ];
    }

    private function timestamp(?string $value): ?string
    {
        return $value ? (new \DateTimeImmutable($value, new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
    }
}
