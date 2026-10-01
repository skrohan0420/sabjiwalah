<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\AuthService;

class AuthController extends BaseController
{
    public function login(): string
    {
        return view('client/auth/login');
    }

    public function attemptLogin()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->attempt((string) $this->request->getPost('email'), (string) $this->request->getPost('password'))) {
            return redirect()->back()->withInput()->with('error', 'Invalid login or inactive account.');
        }

        return $this->redirectByRole();
    }

    public function register(): string
    {
        return view('client/auth/register');
    }

    public function storeRegistration()
    {
        $rules = [
            'name'                  => 'required|max_length[120]',
            'email'                 => 'required|valid_email|max_length[190]|is_unique[users.email]',
            'phone'                 => 'permit_empty|max_length[30]',
            'password'              => 'required|min_length[8]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $auth = new AuthService();

        if (! $auth->registerCustomer($this->request->getPost())) {
            return redirect()->back()->withInput()->with('error', 'Unable to create account.');
        }

        $auth->attempt((string) $this->request->getPost('email'), (string) $this->request->getPost('password'));

        return redirect()->to('/account')->with('message', 'Account created successfully.');
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

    private function redirectByRole()
    {
        return match (session('user_role')) {
            'admin' => redirect()->to('/admin'),
            'delivery' => redirect()->to('/delivery'),
            default => redirect()->to('/account'),
        };
    }
}
