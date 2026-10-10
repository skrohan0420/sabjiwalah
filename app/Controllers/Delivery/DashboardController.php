<?php

namespace App\Controllers\Delivery;

use App\Controllers\BaseController;
use App\Models\DispatchModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        return view('delivery/dashboard', [
            'assignmentCount' => (new DispatchModel())->workload((int) session('user_id')),
        ]);
    }
}
