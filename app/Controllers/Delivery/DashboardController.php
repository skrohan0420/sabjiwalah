<?php

namespace App\Controllers\Delivery;

use App\Controllers\BaseController;
use App\Models\DispatchModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        return $this->page('home', 'Home');
    }

    public function deliveries(): string
    {
        return $this->page('deliveries', 'My deliveries', [
            'assignmentCount' => (new DispatchModel())->workload((int) \App\Services\AuthService::id('delivery')),
        ]);
    }

    public function history(): string
    {
        return $this->page('history', 'Delivery history');
    }

    public function order(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! (new \App\Models\DeliveryOrderModel())->currentForRider((int) \App\Services\AuthService::id('delivery'), $uid)) return $this->response->setStatusCode(404)->setBody('Assigned order not found.');
        return $this->page('deliveries', 'Delivery details', ['orderUid' => $uid, 'assignmentCount' => 1, 'detailView' => true]);
    }

    public function profile(): string
    {
        return $this->page('profile', 'My profile');
    }

    private function page(string $section, string $title, array $data = []): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        return view('delivery/' . (! empty($data['detailView']) ? 'order' : ($section === 'deliveries' ? 'dashboard' : $section)), $data + [
            'section' => $section, 'title' => $title, 'riderName' => (string) (\App\Services\AuthService::identity('delivery')['name'] ?? null),
            'availability' => (new \App\Services\AdminDeliveryPersonnelService())->availabilityFor((string) (\App\Services\AuthService::identity('delivery')['uid'] ?? null)),
        ]);
    }
}
