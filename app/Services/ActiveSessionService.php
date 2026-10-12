<?php
namespace App\Services;

class ActiveSessionService
{
    public function validate(): void
    {
        // Never promote a legacy shared identity into any new role record.
        $legacy = ['user_id', 'user_uid', 'user_name', 'user_email', 'user_phone', 'user_role',
            'is_logged_in', 'auth_proof_version', 'auth_method', 'auth_otp'];
        if (array_intersect_key(session()->get(), array_flip($legacy))) {
            session()->remove([...$legacy, 'checkout_otp', 'checkout_offer']);
            session()->regenerate(true);
        }
        foreach (AuthService::ROLES as $role) AuthService::forRole($role)->user();
    }
}
