<?php

namespace App\Controllers\Delivery;

use App\Controllers\BaseController;
use App\Models\DeliveryAssignmentModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        return view('delivery/dashboard', [
            'assignmentCount' => (new DeliveryAssignmentModel())
                ->where('delivery_user_id', session('user_id'))
                ->countAllResults(),
        ]);
    }
}
