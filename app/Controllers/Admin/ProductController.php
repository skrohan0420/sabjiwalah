<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class ProductController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        return view('admin/products', ['pageTitle' => 'Products', 'activeNav' => 'products']);
    }
}
