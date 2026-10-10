<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\ProductModel;

class HomeController extends BaseController
{
    public function index(): string
    {
        $products = (new ProductModel())
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll(5);

        return view('client/home', [
            'products' => $products,
            'promotions' => (new \App\Services\PromotionService())->home(),
        ]);
    }
}
