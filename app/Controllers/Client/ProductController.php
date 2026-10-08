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

    public function search(): string
    {
        $query = trim((string) $this->request->getGet('q'));
        $products = (new ProductModel())
            ->where('is_active', 1);

        if ($query !== '') {
            $products
                ->groupStart()
                    ->like('name', $query)
                    ->orLike('description', $query)
                    ->orLike('unit', $query)
                ->groupEnd();
        }

        return view('client/products/search', [
            'products' => $products
                ->orderBy('name', 'ASC')
                ->findAll(),
            'query' => $query,
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
            'suggestions' => (new ProductModel())
                ->where('is_active', 1)
                ->where('id !=', (int) $product['id'])
                ->orderBy('name', 'ASC')
                ->findAll(6),
        ]);
    }
}
