<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class LiveMapController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return view('admin/live_map', ['pageTitle' => 'Live map', 'activeNav' => 'live-map']);
    }
}
