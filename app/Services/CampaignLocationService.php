<?php
namespace App\Services;
class CampaignLocationService
{
    public function safe(string $value): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $value)) return false;
        // Decode only for inspection, so encoded control/backslash/protocol-relative paths cannot evade validation.
        $decoded = rawurldecode($value);
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $decoded) || str_starts_with($decoded, '//')) return false;
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) return true;
        $url = parse_url($value);
        return filter_var($value, FILTER_VALIDATE_URL) !== false && $url !== false && ($url['scheme'] ?? '') === 'https'
            && !isset($url['user']) && !isset($url['pass']);
    }
}
