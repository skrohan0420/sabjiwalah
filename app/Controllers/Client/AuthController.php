<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\AuthService;

class AuthController extends BaseController
{
    public function login(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
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
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $auth = new AuthService();

        try { if (! $auth->verifyPhoneOtp(
            (string) $this->request->getPost('phone'),
            (string) $this->request->getPost('otp')
        )) {
            return redirect()->back()->with('error', 'Invalid or expired OTP.');
        } } catch (\App\Services\OtpRateLimitException $e) {
            return redirect()->to(site_url('login'))->with('error', 'Wait ' . $e->retryAfter . ' seconds before trying another OTP.');
        } catch (\App\Services\OtpUnavailableException $e) {
            return redirect()->to(site_url('login'))->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(site_url('login'))->with('error', $e->getMessage());
        } catch (\Throwable) {
            return redirect()->to(site_url('login'))->with('error', 'Unable to complete phone verification. Please try again later.');
        }

        return $this->redirectByRole($this->safeRedirect((string) $this->request->getPost('redirect')));
    }

    public function register()
    {
        $redirect = $this->safeRedirect((string) $this->request->getGet('redirect'));

        return redirect()->to(site_url('login') . ($redirect ? '?redirect=' . rawurlencode($redirect) : ''));
    }

    public function storeRegistration()
    {
        return $this->attemptLogin();
    }

    public function logout()
    {
        (new AuthService())->logout();

        return redirect()->to(site_url(''))->with('message', 'Logged out successfully.');
    }

    public function account(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! session('is_logged_in')) {
            return view('client/account_guest');
        }

        return view('client/account', [
            'user' => (new AuthService())->user(),
        ]);
    }

    private function redirectByRole(?string $redirect = null)
    {
        if (! \App\Services\CustomerSetup::complete((new AuthService())->user())) {
            return redirect()->to(site_url('account'));
        }
        if (session('user_role') === 'customer' && $redirect !== null && $redirect !== '') {
            return redirect()->to(app_asset_url($redirect));
        }

        return match (session('user_role')) {
            'admin' => redirect()->to(site_url('admin')),
            'delivery' => redirect()->to(site_url('delivery')),
            default => redirect()->to(site_url('account')),
        };
    }

    private function safeRedirect(string $redirect): ?string
    {
        $redirect = trim($redirect);

        if ($redirect === '' || ! str_starts_with($redirect, '/')
            || ! (new \App\Services\CampaignLocationService())->safe($redirect)) {
            return null;
        }

        return $redirect;
    }
}
