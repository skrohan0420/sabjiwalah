<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    private const OTP_TTL_SECONDS = 600;

    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? new UserModel();
    }

    public function registerCustomer(array $data): int|false
    {
        return $this->users->insert([
            'name'          => trim((string) ($data['name'] ?? '')),
            'email'         => $this->nullableEmail($data['email'] ?? null),
            'phone'         => $this->normalizePhone((string) ($data['phone'] ?? '')),
            'password_hash' => isset($data['password']) && $data['password'] !== ''
                ? password_hash((string) $data['password'], PASSWORD_DEFAULT)
                : null,
            'role'          => 'customer',
            'status'        => 'active',
        ]);
    }

    public function startPhoneOtp(string $phone, ?string $name = null): array
    {
        $phone = $this->normalizePhone($phone);
        $user = $this->findByPhone($phone);
        $code = (string) random_int(100000, 999999);
        $expiresAt = time() + self::OTP_TTL_SECONDS;

        session()->set('auth_otp', [
            'phone'      => $phone,
            'name'       => trim((string) $name),
            'code'       => $code,
            'expires_at' => $expiresAt,
        ]);

        return [
            'dev_otp'     => $code,
            'expires_at'  => date(DATE_ATOM, $expiresAt),
            'user_exists' => $user !== null,
        ];
    }

    public function verifyPhoneOtp(string $phone, string $code, ?string $name = null): bool
    {
        $phone = $this->normalizePhone($phone);
        $otp = session('auth_otp');

        if (
            ! is_array($otp)
            || time() > (int) ($otp['expires_at'] ?? 0)
            || $phone !== ($otp['phone'] ?? null)
            || $code !== (string) ($otp['code'] ?? '')
        ) {
            return false;
        }

        $user = $this->findByPhone($phone);

        if (! $user) {
            $userId = $this->registerCustomer([
                'name'  => $this->defaultCustomerName($phone),
                'phone' => $phone,
            ]);

            if (! $userId) {
                return false;
            }

            $user = $this->users->find((int) $userId);
        }

        if (! $user || $user['status'] !== 'active') {
            return false;
        }

        $this->loginUser($user);
        session()->remove('auth_otp');

        return true;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users
            ->where('email', strtolower(trim($email)))
            ->first();

        if (
            ! $user
            || $user['status'] !== 'active'
            || ! is_string($user['password_hash'])
            || ! password_verify($password, $user['password_hash'])
        ) {
            return false;
        }

        $this->loginUser($user);

        return true;
    }

    public function logout(): void
    {
        session()->remove([
            'user_id',
            'user_uid',
            'user_name',
            'user_email',
            'user_phone',
            'user_role',
            'is_logged_in',
            'auth_otp',
            'checkout_otp',
        ]);
        session()->regenerate(true);
    }

    public function user(): ?array
    {
        $userId = session('user_id');

        if (! $userId) {
            return null;
        }

        return $this->users->find((int) $userId);
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $payload = [
            'name'  => trim((string) ($data['name'] ?? '')),
            'email' => $this->nullableEmail($data['email'] ?? null),
        ];

        if (! $this->users->update($userId, $payload)) {
            return false;
        }

        $user = $this->users->find($userId);

        if ($user && (int) session('user_id') === $userId) {
            session()->set([
                'user_name'  => $user['name'],
                'user_email' => $user['email'],
            ]);
        }

        return true;
    }

    public function errors(): array
    {
        return $this->users->errors();
    }

    public function check(): bool
    {
        return (bool) session('is_logged_in');
    }

    public function hasRole(string $role): bool
    {
        return $this->check() && session('user_role') === $role;
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        $digits = ltrim($digits, '0');

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    private function findByPhone(string $phone): ?array
    {
        return $this->users
            ->where('phone', $this->normalizePhone($phone))
            ->first();
    }

    private function loginUser(array $user): void
    {
        session()->regenerate(true);
        session()->set([
            'user_id'       => (int) $user['id'],
            'user_uid'      => $user['uid'],
            'user_name'     => $user['name'],
            'user_email'    => $user['email'],
            'user_phone'    => $user['phone'],
            'user_role'     => $user['role'],
            'is_logged_in'  => true,
        ]);
    }

    private function nullableEmail(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function defaultCustomerName(string $phone): string
    {
        $suffix = substr(preg_replace('/\D+/', '', $phone) ?? '', -4);

        return 'Customer' . ($suffix !== '' ? ' ' . $suffix : '');
    }
}
