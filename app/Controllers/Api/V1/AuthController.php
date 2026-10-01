<?php

namespace App\Controllers\Api\V1;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function register()
    {
        $data = $this->requestData();
        $rules = [
            'name'                  => 'required|max_length[120]',
            'email'                 => 'required|valid_email|max_length[190]|is_unique[users.email]',
            'phone'                 => 'permit_empty|max_length[30]',
            'password'              => 'required|min_length[8]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $auth = new AuthService();
        $userId = $auth->registerCustomer($data);

        if (! $userId) {
            return $this->error('Unable to create account', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }

        $auth->attempt((string) $data['email'], (string) $data['password']);

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ], 'Registration successful', ResponseInterface::HTTP_CREATED);
    }

    public function login()
    {
        $data = $this->requestData();
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->attempt((string) $data['email'], (string) $data['password'])) {
            return $this->error('Invalid login or inactive account', ResponseInterface::HTTP_UNAUTHORIZED);
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
