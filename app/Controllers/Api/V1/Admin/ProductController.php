<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\ProductModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends BaseApiController
{
    public function index()
    {
        $query = $this->request->getGet();
        $rules = [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'search'   => 'permit_empty|max_length[120]',
            'active'   => 'permit_empty|in_list[0,1]',
            'sort'     => 'permit_empty|in_list[name,price,stock_quantity,created_at]',
            'dir'      => 'permit_empty|in_list[asc,desc]',
        ];

        if (! $this->validateData($query, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20)));
        $sort = $query['sort'] ?? 'created_at';
        $dir = strtoupper($query['dir'] ?? 'desc');

        $products = new ProductModel();

        if (isset($query['active']) && $query['active'] !== '') {
            $products->where('is_active', (int) $query['active']);
        }

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

    public function show(string $uid)
    {
        $product = (new ProductModel())->findByUid($uid);

        if (! $product) {
            return $this->error('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->success([
            'product' => $this->publicProduct($product),
        ]);
    }

    public function create()
    {
        $data = $this->requestData();
        $rules = $this->rules();

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $products = new ProductModel();
        $id = $products->skipValidation(true)->insert($data);

        if (! $id) {
            return $this->validationError($products->errors());
        }

        return $this->success([
            'product' => $this->publicProduct($products->find($id)),
        ], 'Product created', ResponseInterface::HTTP_CREATED);
    }

    public function update(string $uid)
    {
        $products = new ProductModel();
        $product = $products->findByUid($uid);

        if (! $product) {
            return $this->error('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $id = (int) $product['id'];
        $data = $this->requestData();
        $rules = $this->rules($id);

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        if (! $products->skipValidation(true)->update($id, $data)) {
            return $this->validationError($products->errors());
        }

        return $this->success([
            'product' => $this->publicProduct($products->find($id)),
        ], 'Product updated');
    }

    public function delete(string $uid)
    {
        $products = new ProductModel();
        $product = $products->findByUid($uid);

        if (! $product) {
            return $this->error('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $products->delete((int) $product['id']);

        return $this->success(null, 'Product deleted');
    }

    private function rules(?int $id = null): array
    {
        $slugRule = $id === null
            ? 'required|max_length[190]|is_unique[products.slug]'
            : "required|max_length[190]|is_unique[products.slug,id,{$id}]";

        return [
            'name'           => 'required|max_length[150]',
            'slug'           => $slugRule,
            'description'    => 'permit_empty',
            'image'          => 'permit_empty|max_length[255]',
            'price'          => 'required|decimal|greater_than_equal_to[0]',
            'sale_price'     => 'permit_empty|decimal|greater_than_equal_to[0]',
            'unit'           => 'required|max_length[40]',
            'stock_quantity' => 'required|is_natural',
            'is_active'      => 'permit_empty|in_list[0,1]',
        ];
    }
}
