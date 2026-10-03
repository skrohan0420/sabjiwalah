<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\AuthService;

class AuthController extends BaseController
{
    public function login(): string
    {
        return view('client/auth/login', [
            'redirect' => $this->safeRedirect((string) $this->request->getGet('redirect')),
        ]);
    }

    public function attemptLogin()
    {
        $rules = [
            'phone' => 'required|max_length[30]',
            'otp'   => 'required|numeric|exact_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->verifyPhoneOtp(
            (string) $this->request->getPost('phone'),
            (string) $this->request->getPost('otp')
        )) {
            return redirect()->back()->withInput()->with('error', 'Invalid or expired OTP.');
        }

        return $this->redirectByRole($this->safeRedirect((string) $this->request->getPost('redirect')));
    }

    public function register()
    {
        $redirect = $this->safeRedirect((string) $this->request->getGet('redirect'));

        return redirect()->to('/login' . ($redirect ? '?redirect=' . rawurlencode($redirect) : ''));
    }

    public function storeRegistration()
    {
        return $this->attemptLogin();
    }

    public function logout()
    {
        (new AuthService())->logout();

        return redirect()->to('/')->with('message', 'Logged out successfully.');
    }

    public function account(): string
    {
        return view('client/account', [
            'user' => (new AuthService())->user(),
        ]);
    }

    private function redirectByRole(?string $redirect = null)
    {
        if (session('user_role') === 'customer' && $redirect !== null && $redirect !== '') {
            return redirect()->to($redirect);
        }

        return match (session('user_role')) {
            'admin' => redirect()->to('/admin'),
            'delivery' => redirect()->to('/delivery'),
            default => redirect()->to('/account'),
        };
    }

    private function safeRedirect(string $redirect): ?string
    {
        $redirect = trim($redirect);

        if ($redirect === '' || ! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return null;
        }

        return $redirect;
    }
}
