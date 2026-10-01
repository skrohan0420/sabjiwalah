<?php

namespace App\Controllers\Api\V1;

use App\Models\ProductModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends BaseApiController
{
    public function index()
    {
        $rules = [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'search'   => 'permit_empty|max_length[120]',
            'sort'     => 'permit_empty|in_list[name,price,created_at]',
            'dir'      => 'permit_empty|in_list[asc,desc]',
        ];
        $query = $this->request->getGet();

        if (! $this->validateData($query, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20)));
        $sort = $query['sort'] ?? 'name';
        $dir = strtoupper($query['dir'] ?? 'asc');

        $products = new ProductModel();
        $products->where('is_active', 1);

        if (! empty($query['search'])) {
            $products->like('name', (string) $query['search']);
        }

        $rows = $products
            ->orderBy($sort, $dir)
            ->paginate($perPage, 'default', $page);

        return $this->success([
            'items' => array_map(fn (array $product): array => $this->publicProduct($product), $rows),
            'pager' => [
                'current_page' => $products->pager->getCurrentPage(),
                'per_page'     => $perPage,
                'total'        => $products->pager->getTotal(),
                'page_count'   => $products->pager->getPageCount(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $product = (new ProductModel())
            ->where('is_active', 1)
            ->find($id);

        if (! $product) {
            return $this->error('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->success([
            'product' => $this->publicProduct($product),
        ]);
    }
}
