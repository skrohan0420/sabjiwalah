<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\AuthService;

class AuthController extends BaseController
{
    protected string $authRole = 'customer';
    protected function loginPath(): string { return $this->authRole === 'customer' ? 'login' : $this->authRole . '/login'; }
    public function login(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        return view('client/auth/login', [
            'authRole' => $this->authRole, 'loginPath' => $this->loginPath(),
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
            return redirect()->to(site_url($this->loginPath()))->with('errors', $this->validator->getErrors());
        }

        $auth = AuthService::forRole($this->authRole);

        try { if (! $auth->verifyPhoneOtp(
            (string) $this->request->getPost('phone'),
            (string) $this->request->getPost('otp')
        )) {
            return redirect()->to(site_url($this->loginPath()))->with('error', 'Invalid or expired OTP.');
        } } catch (\App\Services\OtpRateLimitException $e) {
            return redirect()->to(site_url($this->loginPath()))->with('error', 'Wait ' . $e->retryAfter . ' seconds before trying another OTP.');
        } catch (\App\Services\OtpUnavailableException $e) {
            return redirect()->to(site_url($this->loginPath()))->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(site_url($this->loginPath()))->with('error', $e->getMessage());
        } catch (\Throwable) {
            return redirect()->to(site_url($this->loginPath()))->with('error', 'Unable to complete phone verification. Please try again later.');
        }

        return $this->redirectByRole($this->safeRedirect((string) $this->request->getPost('redirect')));
    }

    public function register()
    {
        $redirect = $this->safeRedirect((string) $this->request->getGet('redirect'));

        return redirect()->to(site_url($this->loginPath()) . ($redirect ? '?redirect=' . rawurlencode($redirect) : ''));
    }

    public function storeRegistration()
    {
        return $this->attemptLogin();
    }

    public function logout()
    {
        AuthService::forRole($this->authRole)->logout();

        return redirect()->to(site_url($this->loginPath()))->with('message', 'Logged out successfully.');
    }

    public function account(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! AuthService::forRole($this->authRole)->check()) {
            return view('client/account_guest');
        }

        return view('client/account', [
            'user' => AuthService::forRole($this->authRole)->user(),
        ]);
    }

    private function redirectByRole(?string $redirect = null)
    {
        if (! \App\Services\CustomerSetup::complete(AuthService::forRole($this->authRole)->user())) {
            return redirect()->to(site_url('account'));
        }
        if ($this->authRole === 'customer' && $redirect !== null && $redirect !== '') {
            return redirect()->to(app_asset_url($redirect));
        }

        return match ($this->authRole) {
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

        $path = rawurldecode((string) parse_url($redirect, PHP_URL_PATH));
        if (str_contains($path, '..')) return null;
        if ($this->authRole === 'customer' && !preg_match('#^/(?:account|checkout|search|(?:products|orders)(?:/[^/]+)?)?/?$#D', $path)) return null;
        if ($this->authRole !== 'customer' && !str_starts_with($path, '/' . $this->authRole . '/')) return null;
        return $redirect;
    }
}
