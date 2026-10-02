<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? new UserModel();
    }

    public function registerCustomer(array $data): int|false
    {
        return $this->users->insert([
            'name'          => trim((string) ($data['name'] ?? '')),
            'email'         => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone'         => trim((string) ($data['phone'] ?? '')),
            'password_hash' => password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT),
            'role'          => 'customer',
            'status'        => 'active',
        ]);
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users
            ->where('email', strtolower(trim($email)))
            ->first();

        if (
            ! $user
            || $user['status'] !== 'active'
            || ! password_verify($password, $user['password_hash'])
        ) {
            return false;
        }

        session()->regenerate(true);
        session()->set([
            'user_id'       => (int) $user['id'],
            'user_uid'      => $user['uid'],
            'user_name'     => $user['name'],
            'user_email'    => $user['email'],
            'user_role'     => $user['role'],
            'is_logged_in'  => true,
        ]);

        return true;
    }

    public function logout(): void
    {
        session()->remove([
            'user_id',
            'user_uid',
            'user_name',
            'user_email',
            'user_role',
            'is_logged_in',
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

    public function check(): bool
    {
        return (bool) session('is_logged_in');
    }

    public function hasRole(string $role): bool
    {
        return $this->check() && session('user_role') === $role;
    }
}
