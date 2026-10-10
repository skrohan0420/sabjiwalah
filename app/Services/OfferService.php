<?php
namespace App\Services;

class OfferService
{
    public function evaluate(string $code, float $subtotal, bool $lock = false): array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/D', $code)) throw new \InvalidArgumentException('Enter a valid coupon code.');
        $db = db_connect();
        // All consuming requests lock this same offer before a current-read usage check.
        $row = $lock
            ? $db->query('SELECT * FROM ' . $db->protectIdentifiers('offers', true) . ' WHERE code = ? FOR UPDATE', [$code])->getRowArray()
            : $db->table('offers')->where('code', $code)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        if (!$row || !(bool)$row['is_active'] || ($row['starts_at'] && $row['starts_at'] > $now) || ($row['ends_at'] && $row['ends_at'] <= $now))
            throw new \InvalidArgumentException('This coupon is unavailable or outside its scheduled dates.');
        $cents = (int)round($subtotal * 100);
        if ($cents <= 0 || $cents < (int)round((float)$row['minimum_order_amount'] * 100)) throw new \InvalidArgumentException('The cart does not meet this coupon’s minimum subtotal.');
        if (!in_array($row['type'], ['percentage', 'fixed'], true) || !is_numeric($row['value']) || (float)$row['value'] <= 0
            || ($row['type'] === 'percentage' && (float)$row['value'] > 100)
            || ($row['maximum_discount'] !== null && (float)$row['maximum_discount'] <= 0)) throw new \InvalidArgumentException('This coupon has invalid discount settings.');
        $usage = $row['usage_limit'] === null ? 0 : ($lock
            ? (int)$db->query('SELECT COUNT(*) AS used FROM ' . $db->protectIdentifiers('offer_redemptions', true) . ' WHERE offer_id = ? FOR UPDATE', [$row['id']])->getRowArray()['used']
            : $db->table('offer_redemptions')->where('offer_id', $row['id'])->countAllResults());
        if ($row['usage_limit'] !== null && $usage >= (int)$row['usage_limit']) throw new \InvalidArgumentException('This coupon’s usage limit has been reached.');
        $value = (int)round((float)$row['value'] * 100);
        $discount = $row['type'] === 'percentage' ? (int)round($cents * $value / 10000) : $value;
        if ($row['maximum_discount'] !== null) $discount = min($discount, (int)round((float)$row['maximum_discount'] * 100));
        $discount = max(0, min($discount, $cents));
        if ($discount === 0) throw new \InvalidArgumentException('This coupon does not produce a discount for this cart.');
        return ['id' => (int)$row['id'], 'code' => $row['code'], 'discount_amount' => (float)($discount / 100),
            'revision' => hash('sha256', json_encode(array_intersect_key($row, array_flip(['uid', 'code', 'type', 'value', 'minimum_order_amount', 'maximum_discount', 'starts_at', 'ends_at', 'usage_limit', 'is_active'])), JSON_THROW_ON_ERROR))];
    }

    public function record(int $orderId, array $offer): void
    {
        if (!db_connect()->table('offer_redemptions')->insert(['order_id' => $orderId, 'offer_id' => $offer['id'], 'code' => $offer['code'],
            'discount_amount' => $offer['discount_amount'], 'created_at' => date('Y-m-d H:i:s')])) throw new \RuntimeException('Unable to record coupon usage.');
    }
}
