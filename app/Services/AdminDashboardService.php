<?php

namespace App\Services;

use App\Models\AdminDashboardModel;
use DateTimeImmutable;
use DateTimeZone;

class AdminDashboardService
{
    public const LOW_STOCK_THRESHOLD = 5;
    public const BUSINESS_TIMEZONE = 'Asia/Kolkata';

    public function __construct(private ?AdminDashboardModel $dashboard = null)
    {
    }

    public function overview(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $businessNow = $now->setTimezone(new DateTimeZone(self::BUSINESS_TIMEZONE));
        // Existing order timestamps are written with the configured application timezone.
        $storageTimezone = new DateTimeZone(config('App')->appTimezone);
        $midnight = $businessNow->setTime(0, 0);
        $data = ($this->dashboard ?? new AdminDashboardModel())->overview(
            $midnight->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
            $midnight->modify('+1 day')->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
            self::LOW_STOCK_THRESHOLD,
        );
        foreach ($data['totals'] as $key => $value) {
            $data['totals'][$key] = $key === 'sales_today' ? (float) $value : (int) $value;
        }
        $data['totals']['attention_count'] = $data['totals']['pending'] + $data['totals']['ready_for_delivery'] + $data['totals']['delivery_failed'];
        foreach (['recent_orders', 'attention_orders'] as $key) {
            foreach ($data[$key] as &$order) {
                $order['total_amount'] = (float) $order['total_amount'];
                $order['created_at'] = $order['created_at'] ? (new DateTimeImmutable($order['created_at'], $storageTimezone))->format(DATE_ATOM) : null;
            }
            unset($order);
        }
        $data['business_date'] = $businessNow->format('Y-m-d');
        $data['timezone'] = self::BUSINESS_TIMEZONE;
        $data['generated_at'] = $businessNow->format(DATE_ATOM);
        $data['low_stock_threshold'] = self::LOW_STOCK_THRESHOLD;
        return $data;
    }
}
