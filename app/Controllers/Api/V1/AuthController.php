<?php

namespace App\Controllers\Api\V1;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function startOtp()
    {
        $data = $this->requestData();
        $rules = [
            'phone' => 'required|max_length[30]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $otp = (new AuthService())->startPhoneOtp((string) $data['phone']);

        return $this->success($otp, 'OTP generated for auth testing.');
    }

    public function verifyOtp()
    {
        $data = $this->requestData();
        $rules = [
            'phone' => 'required|max_length[30]',
            'otp'   => 'required|numeric|exact_length[6]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->verifyPhoneOtp(
            (string) $data['phone'],
            (string) $data['otp']
        )) {
            return $this->error('Invalid or expired OTP.', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ], 'Login successful');
    }

    public function register()
    {
        $data = $this->requestData();
        $rules = [
            'phone' => 'required|max_length[30]',
            'otp'   => 'required|numeric|exact_length[6]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->verifyPhoneOtp((string) $data['phone'], (string) $data['otp'])) {
            return $this->error('Invalid OTP, expired OTP, or unable to create account', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ], 'Registration successful', ResponseInterface::HTTP_CREATED);
    }

    public function login()
    {
        $data = $this->requestData();
        $rules = [
            'phone' => 'required|max_length[30]',
            'otp'   => 'required|numeric|exact_length[6]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->verifyPhoneOtp(
            (string) $data['phone'],
            (string) $data['otp']
        )) {
            return $this->error('Invalid or expired OTP.', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ], 'Login successful');
    }

    public function logout()
    {
        (new AuthService())->logout();

        return $this->success(null, 'Logout successful');
    }

    public function me()
    {
        $auth = new AuthService();

        if (! $auth->check()) {
            return $this->error('Unauthenticated', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ]);
    }
}
