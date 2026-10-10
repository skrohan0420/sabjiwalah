<?php

namespace App\Services;

use App\Models\UserModel;

/** Revalidate session identity before authorization or customer setup checks. */
class ActiveSessionService
{
    public function validate(): void
    {
        if (! session('is_logged_in')) return;
        // Revoke legacy/testing OTP sessions during production rollout, including pre-existing cookies.
        if (ENVIRONMENT === 'production' && (session('auth_proof_version') !== AuthService::SESSION_PROOF_VERSION
            || ! in_array(session('auth_method'), ['password', 'sms'], true))) {
            (new AuthService())->logout();
            return;
        }
        $user = (new UserModel())->select('id, uid, name, email, phone, role, status')->find((int) session('user_id'));
        if (! $user || $user['status'] !== 'active' || $user['uid'] !== session('user_uid') || $user['role'] !== session('user_role')) {
            (new AuthService())->logout();
            return;
        }
        session()->set(['user_name' => $user['name'], 'user_email' => $user['email'], 'user_phone' => $user['phone']]);
    }
}
