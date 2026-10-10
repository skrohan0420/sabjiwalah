<?php

namespace App\Services;

class OtpService
{
    public const TTL_SECONDS = 600;
    public const WINDOW_SECONDS = 900;
    public const RESEND_SECONDS = 60;
    public const PHONE_SEND_LIMIT = 5;
    public const PHONE_VERIFY_LIMIT = 5;
    public const IP_SEND_LIMIT = 20;
    public const IP_VERIFY_LIMIT = 60;

    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (! preg_match('/^\+?[\d\s().-]{10,29}$/D', $phone)) {
            throw new \InvalidArgumentException('Enter a valid 10-digit Indian phone number.');
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
        elseif (strlen($digits) === 14 && str_starts_with($digits, '0091')) $digits = substr($digits, 4);
        if (! preg_match('/^[6-9]\d{9}$/D', $digits)) {
            throw new \InvalidArgumentException('Enter a valid 10-digit Indian phone number.');
        }
        return $digits;
    }

    private function requireLocalDevelopment(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $localHost = preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/iD', $host) === 1;
        // A tunnel/proxy often connects from loopback. Its headers or public hostname must not
        // turn a remote requester into an administrator through a displayed testing code.
        foreach (['HTTP_FORWARDED','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED_HOST','HTTP_X_FORWARDED_PROTO','HTTP_X_REAL_IP'] as $header) {
            if (trim((string) ($_SERVER[$header] ?? '')) !== '') throw new OtpUnavailableException();
        }
        if (ENVIRONMENT !== 'development' || ! config('Otp')->developmentMode
            || ($host !== '' && !$localHost) || (PHP_SAPI !== 'cli' && !$localHost)
            || ! in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
            throw new OtpUnavailableException();
        }
        return $ip;
    }

    public function start(string $purpose, string $phone): array
    {
        $ip = $this->requireLocalDevelopment();
        $phone = self::normalizePhone($phone);
        $key = $this->sessionKey($purpose);
        $this->consume([
            ['key' => $purpose . ':send:phone:' . $phone, 'limit' => self::PHONE_SEND_LIMIT, 'cooldown' => self::RESEND_SECONDS],
            ['key' => 'send:ip:' . $ip, 'limit' => self::IP_SEND_LIMIT, 'cooldown' => 0],
        ]);
        $code = (string) random_int(100000, 999999);
        $expiresAt = time() + self::TTL_SECONDS;
        session()->set($key, [
            'version' => 1,
            'phone' => $phone,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
            'verified' => false,
        ]);
        return ['dev_otp' => $code, 'expires_at' => date(DATE_ATOM, $expiresAt), 'retry_after' => self::RESEND_SECONDS];
    }

    public function verify(string $purpose, string $phone, string $code): bool
    {
        $ip = $this->requireLocalDevelopment();
        $phone = self::normalizePhone($phone);
        $key = $this->sessionKey($purpose);
        $this->consume([
            ['key' => $purpose . ':verify:phone:' . $phone, 'limit' => self::PHONE_VERIFY_LIMIT, 'cooldown' => 0],
            ['key' => 'verify:ip:' . $ip, 'limit' => self::IP_VERIFY_LIMIT, 'cooldown' => 0],
        ]);
        $otp = session($key);
        if (! is_array($otp) || ($otp['version'] ?? null) !== 1 || time() >= (int) ($otp['expires_at'] ?? 0)) {
            session()->remove($key);
            return false;
        }
        if (! preg_match('/^\d{6}$/D', $code) || ! hash_equals((string) ($otp['phone'] ?? ''), $phone)
            || ! is_string($otp['code_hash'] ?? null) || ! password_verify($code, $otp['code_hash'])) {
            return false;
        }
        if ($purpose === 'auth') session()->remove($key);
        else {
            unset($otp['code_hash']);
            $otp['verified'] = true;
            session()->set($key, $otp);
        }
        return true;
    }

    public function verifiedForCheckout(string $phone): bool
    {
        $this->requireLocalDevelopment();
        $phone = self::normalizePhone($phone);
        $otp = session('checkout_otp');
        return is_array($otp) && ($otp['version'] ?? null) === 1 && ($otp['verified'] ?? false) === true
            && time() < (int) ($otp['expires_at'] ?? 0)
            && hash_equals((string) ($otp['phone'] ?? ''), $phone);
    }

    private function sessionKey(string $purpose): string
    {
        if (! in_array($purpose, ['auth', 'checkout'], true)) throw new \LogicException('Unknown OTP purpose.');
        return $purpose . '_otp';
    }

    /** Atomic database budgets survive new browser sessions and code resends. */
    private function consume(array $budgets): void
    {
        $db = db_connect();
        foreach ($budgets as &$budget) $budget['key'] = hash('sha256', $budget['key']);
        unset($budget);
        usort($budgets, static fn ($a, $b) => strcmp($a['key'], $b['key']));
        $now = time();
        if (! $db->transBegin()) throw new \RuntimeException('Unable to check OTP limits.');
        try {
            $table = $db->protectIdentifiers('otp_rate_limits', true);
            foreach ($budgets as $budget) {
                // The unique key makes even the first concurrent request acquire a row lock.
                if (! $db->query('INSERT INTO ' . $table . ' (bucket_key,attempts,expires_at,last_used_at) VALUES (?,0,?,0) ON DUPLICATE KEY UPDATE bucket_key=VALUES(bucket_key)', [$budget['key'], $now + self::WINDOW_SECONDS])) {
                    throw new \RuntimeException('Unable to check OTP limits.');
                }
                $row = $db->query('SELECT * FROM ' . $table . ' WHERE bucket_key=? FOR UPDATE', [$budget['key']])->getRowArray();
                $expired = (int) $row['expires_at'] <= $now;
                $count = $expired ? 0 : (int) $row['attempts'];
                $expires = $expired ? $now + self::WINDOW_SECONDS : (int) $row['expires_at'];
                if ($count >= $budget['limit']) throw new OtpRateLimitException(max(1, $expires - $now));
                $remaining = (int) $row['last_used_at'] + $budget['cooldown'] - $now;
                if ($remaining > 0) throw new OtpRateLimitException($remaining);
                if (! $db->table('otp_rate_limits')->where('bucket_key', $budget['key'])->update([
                    'attempts' => $count + 1, 'expires_at' => $expires, 'last_used_at' => $now,
                ])) throw new \RuntimeException('Unable to check OTP limits.');
            }
            if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Unable to check OTP limits.');
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
