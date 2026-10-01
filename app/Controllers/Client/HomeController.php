<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;

class HomeController extends BaseController
{
    public function index(): string
    {
        return view('client/home');
    }
}
