<?php
// Test-only helper. Call only after shadowing users on a disposable connection.
function fixtureIdentity(int $id): void
{
    session()->remove(['customer_auth', 'admin_auth', 'delivery_auth']);
    if ($id === 0) return;
    $user = (new App\Models\UserModel())->find($id);
    if (!$user) throw new RuntimeException('Missing fixture user.');
    session()->set($user['role'] . '_auth', [
        'id' => (int) $user['id'], 'uid' => $user['uid'], 'role' => $user['role'], 'phone' => $user['phone'],
        'proof_version' => App\Services\AuthService::SESSION_PROOF_VERSION, 'method' => 'local_development',
        'purpose' => $user['role'] . '_login', 'authenticated_at' => time(),
    ]);
}
