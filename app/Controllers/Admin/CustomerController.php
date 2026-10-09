<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class CustomerController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return view('admin/customers', ['pageTitle' => 'Customers', 'activeNav' => 'customers']);
    }
}
