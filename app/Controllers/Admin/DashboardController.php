<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        return view('admin/dashboard', [
            'productCount'  => (new ProductModel())->countAllResults(),
            'orderCount'    => (new OrderModel())->countAllResults(),
            'customerCount' => (new UserModel())->where('role', 'customer')->countAllResults(),
        ]);
    }
}
