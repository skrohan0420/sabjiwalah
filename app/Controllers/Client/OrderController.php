<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Services\AuthService;

class OrderController extends BaseController
{
    public function show(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0')->setHeader('Pragma', 'no-cache');
        try { $detail = (new \App\Services\CustomerOrderService())->detail($uid, (int) session('user_id')); }
        catch (\OutOfBoundsException) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        return view('client/orders/show', ['detail'=>$detail]);
    }
    public function index(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0');
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
