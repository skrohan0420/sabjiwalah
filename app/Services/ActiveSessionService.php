<?php

namespace App\Services;

use App\Models\UserModel;

/** Revalidate session identity before authorization or customer setup checks. */
class ActiveSessionService
{
    public function validate(): void
    {
        if (! session('is_logged_in')) return;
        $user = (new UserModel())->select('id, uid, name, email, phone, role, status')->find((int) session('user_id'));
        if (! $user || $user['status'] !== 'active' || $user['uid'] !== session('user_uid') || $user['role'] !== session('user_role')) {
            (new AuthService())->logout();
            return;
        }
        session()->set(['user_name' => $user['name'], 'user_email' => $user['email'], 'user_phone' => $user['phone']]);
    }
}
