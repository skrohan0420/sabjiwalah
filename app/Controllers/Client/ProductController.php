<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProductController extends BaseController
{
    public function index(): string
    {
        return view('client/products/index', [
            'products' => (new ProductModel())
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->findAll(),
        ]);
    }

    public function show(string $uidOrSlug): string
    {
        $product = (new ProductModel())
            ->where('is_active', 1)
            ->groupStart()
                ->where('uid', $uidOrSlug)
                ->orWhere('slug', $uidOrSlug)
            ->groupEnd()
            ->first();

        if (! $product) {
            throw PageNotFoundException::forPageNotFound('Product not found.');
        }

        return view('client/products/show', [
            'product' => $product,
        ]);
    }
}
