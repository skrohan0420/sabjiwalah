<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Services\CheckoutSummaryService;

class CheckoutController extends BaseController
{
    public function index(): string
    {
        $user = (new AuthService())->user();

        return view('client/checkout/index', [
            'user'       => $user,
            'isLoggedIn' => $user !== null && ($user['role'] ?? null) === 'customer',
            'checkout'   => (new CheckoutSummaryService())->summary(),
            'pageTitle'  => 'Checkout',
            'backUrl'    => '/',
        ]);
    }
}
