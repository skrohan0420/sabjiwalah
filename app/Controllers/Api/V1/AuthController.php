<?php

namespace App\Controllers\Api\V1;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function startOtp()
    {
        return $this->otpResponse(function () {
            $data = $this->otpInput(false);
            return $this->success((new AuthService())->startPhoneOtp($data['phone']), 'OTP generated for local development testing.');
        });
    }

    public function verifyOtp()
    {
        return $this->verifyLogin();
    }

    public function register()
    {
        return $this->verifyLogin(ResponseInterface::HTTP_CREATED);
    }

    public function login()
    {
        return $this->verifyLogin();
    }

    private function verifyLogin(int $status = ResponseInterface::HTTP_OK)
    {
        return $this->otpResponse(function () use ($status) {
            $data = $this->otpInput(true);
            $auth = new AuthService();
            if (! $auth->verifyPhoneOtp($data['phone'], $data['otp'])) {
                return $this->error('Invalid or expired OTP.', ResponseInterface::HTTP_UNAUTHORIZED);
            }
            return $this->success(['user' => $this->publicUser($auth->user())], 'Login successful', $status);
        });
    }

    private function otpInput(bool $verify): array
    {
        $data = $this->requestData();
        $allowed = $verify ? ['phone', 'otp'] : ['phone'];
        if (array_diff(array_keys($data), $allowed) || ! is_string($data['phone'] ?? null)
            || ($verify && (! is_string($data['otp'] ?? null) || ! preg_match('/^\d{6}$/D', $data['otp'])))) {
            throw new \InvalidArgumentException('Enter only a phone number and, when verifying, a six-digit OTP.');
        }
        $data['phone'] = \App\Services\OtpService::normalizePhone($data['phone']);
        return $data;
    }

    public function logout()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        (new AuthService())->logout();

        return $this->success(null, 'Logout successful');
    }

    public function me()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $auth = new AuthService();

        if (! $auth->check()) {
            return $this->error('Unauthenticated', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ]);
    }
}
