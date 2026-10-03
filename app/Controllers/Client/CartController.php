<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;

class CartController extends BaseController
{
    public function index(): string
    {
        return view('client/cart/index');
    }
}
