<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class DeliveryPersonnelController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return view('admin/delivery-personnel', ['pageTitle' => 'Delivery personnel', 'activeNav' => 'delivery']);
    }
}
