<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\AdminDashboardService;

class DashboardController extends BaseApiController
{
    public function show()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        try {
            return $this->success((new AdminDashboardService())->overview());
        } catch (\Throwable $exception) {
            log_message('error', 'Admin dashboard could not be loaded: {type}', ['type' => $exception::class]);
            return $this->error('Dashboard is temporarily unavailable. Please try again.', 503);
        }
    }
}
