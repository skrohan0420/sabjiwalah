<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Services\AuthService;

class OrderController extends BaseController
{
    public function index(): string
    {
        $user = (new AuthService())->user();
        $orders = [];

        if ($user && ($user['role'] ?? null) === 'customer') {
            $orders = (new OrderModel())
                ->where('user_id', (int) $user['id'])
                ->orderBy('created_at', 'DESC')
                ->findAll();

            $items = new OrderItemModel();

            foreach ($orders as &$order) {
                $order['item_count'] = $items
                    ->where('order_id', (int) $order['id'])
                    ->countAllResults();
            }
            unset($order);
        }

        return view('client/orders/index', [
            'isLoggedIn' => $user !== null && ($user['role'] ?? null) === 'customer',
            'orders'     => $orders,
        ]);
    }
}
