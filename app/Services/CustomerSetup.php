<?php

namespace App\Services;

class CustomerSetup
{
    public static function complete(?array $user): bool
    {
        if (! $user || ($user['role'] ?? '') !== 'customer') {
            return true;
        }

        return self::validName((string) ($user['name'] ?? ''));
    }

    public static function validName(string $name): bool
    {
        $name = trim($name);

        return mb_strlen($name) >= 2 && mb_strlen($name) <= 120
            && ! preg_match('/^Customer(?: \d+)?$/i', $name)
            && ! preg_match('/[\x00-\x1f\x7f]/', $name);
    }

    public static function validPin(mixed $pin): bool
    {
        return is_array($pin)
            && isset($pin['latitude'], $pin['longitude'])
            && is_numeric($pin['latitude']) && is_numeric($pin['longitude'])
            && is_finite((float) $pin['latitude']) && is_finite((float) $pin['longitude'])
            && abs((float) $pin['latitude']) <= 90 && abs((float) $pin['longitude']) <= 180;
    }
}
