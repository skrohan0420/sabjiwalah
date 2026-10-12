<?php
namespace App\Services;

use App\Models\UserModel;

/** One explicit identity within the shared browser session. */
class AuthService
{
    public const SESSION_PROOF_VERSION = 2;
    public const ROLES = ['customer', 'admin', 'delivery'];
    private UserModel $users;

    public function __construct(?UserModel $users = null, private string $role = 'customer')
    {
        if (!in_array($role, self::ROLES, true)) throw new \InvalidArgumentException('Unknown authentication role.');
        $this->users = $users ?? new UserModel();
    }
    public static function forRole(string $role): self { return new self(null, $role); }
    public static function identity(string $role): ?array { return self::forRole($role)->user(); }
    public static function id(string $role): ?int { return self::identity($role)['id'] ?? null; }

    public function registerCustomer(array $data): int|false
    {
        return $this->users->insert([
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))) ?: null,
            'phone' => OtpService::normalizePhone((string) ($data['phone'] ?? '')),
            'password_hash' => null, 'role' => 'customer', 'status' => 'active',
        ]);
    }
    public function startPhoneOtp(string $phone, ?string $name = null): array
    {
        $phone = OtpService::normalizePhone($phone);
        // Enforce provider restrictions and abuse budgets even for unknown accounts.
        $otp = (new OtpService())->start($this->role . '_login', $phone);
        $user = $this->findByPhone($phone);
        if (($user && $user['status'] !== 'active') || (!$user && $this->role !== 'customer')) {
            session()->remove($this->role . '_login_otp');
            throw new \InvalidArgumentException('An active ' . $this->role . ' account is required. Contact an administrator.');
        }
        return $otp + ['user_exists' => $user !== null, 'user_name' => $user['name'] ?? null];
    }
    public function verifyPhoneOtp(string $phone, string $code, ?string $name = null): bool
    {
        $phone = OtpService::normalizePhone($phone);
        if (!(new OtpService())->verify($this->role . '_login', $phone, $code)) return false;
        $user = $this->findByPhone($phone);
        if (!$user && $this->role === 'customer') {
            try {
                $id = $this->registerCustomer(['name' => 'Customer ' . substr($phone, -4), 'phone' => $phone]);
                $user = $id ? $this->users->find($id) : $this->findByPhone($phone);
            } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
                $user = $this->findByPhone($phone);
                if (!$user) throw $e;
            }
        }
        if (!$user || $user['role'] !== $this->role || $user['status'] !== 'active') return false;
        if ($this->role === 'customer' && (session('customer_auth')['id'] ?? null) !== (int) $user['id']) {
            session()->remove(['checkout_otp', 'checkout_offer', 'delivery_location']);
        }
        session()->regenerate(true);
        session()->set($this->role . '_auth', [
            'id' => (int) $user['id'], 'uid' => $user['uid'], 'role' => $this->role,
            'method' => 'local_development', 'proof_version' => self::SESSION_PROOF_VERSION,
            'phone' => $phone, 'purpose' => $this->role . '_login', 'authenticated_at' => time(),
        ]);
        return true;
    }
    public function logout(): void
    {
        if ($this->role === 'delivery' && is_array(session('delivery_auth'))) {
            try { (new DeliveryLocationService())->stop((int) session('delivery_auth')['id']); }
            catch (\Throwable) { /* Expiry/pruning handles unavailable storage. */ }
        }
        session()->remove([$this->role . '_auth', $this->role . '_login_otp']);
        if ($this->role === 'customer') session()->remove(['checkout_otp', 'checkout_offer', 'delivery_location']);
        session()->regenerate(true);
    }
    public function user(): ?array
    {
        $proof = session($this->role . '_auth');
        if ($proof === null) return null;
        if (!is_array($proof)) { $this->logout(); return null; }
        $valid = ($proof['proof_version'] ?? null) === self::SESSION_PROOF_VERSION
            && ($proof['role'] ?? null) === $this->role && ($proof['purpose'] ?? null) === $this->role . '_login'
            && is_int($proof['authenticated_at'] ?? null) && $proof['authenticated_at'] <= time()
            && in_array($proof['method'] ?? null, ENVIRONMENT === 'production' ? ['sms'] : ['sms', 'local_development'], true);
        $user = $valid ? $this->users->find((int) ($proof['id'] ?? 0)) : null;
        if (!$user || $user['status'] !== 'active' || $user['role'] !== $this->role
            || $user['uid'] !== ($proof['uid'] ?? null) || $user['phone'] !== ($proof['phone'] ?? null)) {
            $this->logout();
            return null;
        }
        return $user;
    }
    public function updateProfile(int $userId, array $data): bool
    {
        if ((int) ($this->user()['id'] ?? 0) !== $userId) return false;
        return $this->users->update($userId, ['name' => trim((string) ($data['name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))) ?: null]);
    }
    public function errors(): array { return $this->users->errors(); }
    public function check(): bool { return $this->user() !== null; }
    public function hasRole(string $role): bool { return $role === $this->role && $this->check(); }
    public function normalizePhone(string $phone): string { return OtpService::normalizePhone($phone); }
    private function findByPhone(string $phone): ?array
    {
        return $this->users->where('phone', $phone)->where('role', $this->role)->first();
    }
}
