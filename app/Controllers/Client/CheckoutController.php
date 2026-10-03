<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\AuthService;

class CheckoutController extends BaseController
{
    public function index(): string
    {
        return view('client/checkout/index', [
            'user' => (new AuthService())->user(),
        ]);
    }
}
