<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class DispatchController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return view('admin/dispatch', ['pageTitle' => 'Dispatch', 'activeNav' => 'dispatch']);
    }
}
