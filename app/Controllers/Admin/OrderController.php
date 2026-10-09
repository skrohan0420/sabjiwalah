<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class OrderController extends BaseController
{
    public function index(): string
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return view('admin/orders', ['pageTitle' => 'Orders', 'activeNav' => 'orders']);
    }
}
