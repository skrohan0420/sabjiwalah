<?php

namespace App\Controllers\Api\V1;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AccountController extends BaseApiController
{
    public function profile()
    {
        $auth = new AuthService();

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ]);
    }

    public function updateProfile()
    {
        $data = $this->requestData();
        $rules = [
            'name'  => 'required|max_length[120]',
            'email' => 'permit_empty|valid_email|max_length[190]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $auth = new AuthService();
        $user = $auth->user();

        if (! $user) {
            return $this->error('Unauthenticated', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if (! \App\Services\CustomerSetup::validName((string) ($data['name'] ?? ''))) {
            return $this->validationError(['name' => 'Enter your real name (2–120 characters).']);
        }

        if (! \App\Services\CustomerSetup::complete($user)
            && ! \App\Services\CustomerSetup::validPin($data['delivery_location'] ?? null)) {
            return $this->validationError(['delivery_location' => 'Confirm your delivery location to finish setup.']);
        }

        if (! $auth->updateProfile((int) $user['id'], $data)) {
            return $this->error('Unable to update profile', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $auth->errors());
        }

        if (\App\Services\CustomerSetup::validPin($data['delivery_location'] ?? null)) {
            session()->set('delivery_location', $data['delivery_location']);
        }

        return $this->success([
            'user' => $this->publicUser($auth->user()),
        ], 'Profile updated');
    }
}
