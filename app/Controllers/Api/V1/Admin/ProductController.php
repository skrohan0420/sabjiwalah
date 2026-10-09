<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\ProductModel;
use App\Services\AdminProductInput;
use App\Services\ProductImageService;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $query = $this->request->getGet();
        $rules = [
            'page'     => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]',
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
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20) ?: 20));
        $sort = ($query['sort'] ?? '') ?: 'created_at';
        $dir = strtoupper(($query['dir'] ?? '') ?: 'desc');

        $products = new ProductModel();

        if (isset($query['active']) && $query['active'] !== '') {
            $products->where('is_active', (int) $query['active']);
        }

        if (! empty($query['search'])) {
            $products->like('name', (string) $query['search']);
        }

        $rows = $products
            ->orderBy($sort, $dir)
            ->orderBy('id', $dir)
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
        $this->response->setHeader('Cache-Control', 'no-store, private');
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
        $data = AdminProductInput::normalize($this->requestData());
        $rules = AdminProductInput::rules();

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }
        if ($data['sale_price'] !== null && (float) $data['sale_price'] > (float) $data['price']) {
            return $this->validationError(['sale_price' => 'Sale price cannot exceed regular price.']);
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
        $input = $this->requestData();
        $data = AdminProductInput::normalize($input, $product);
        $rules = AdminProductInput::rules($id);

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }
        if ($data['sale_price'] !== null && (float) $data['sale_price'] > (float) $data['price']) {
            return $this->validationError(['sale_price' => 'Sale price cannot exceed regular price.']);
        }
        if (array_key_exists('expected_stock_quantity', $input)) {
            if (! $this->validateData($input, ['expected_stock_quantity' => 'required|is_natural|less_than_equal_to[4294967295]'])) {
                return $this->validationError($this->validator->getErrors());
            }
            $products->where('stock_quantity', (int) $input['expected_stock_quantity']);
        }

        $changes = array_intersect_key($data, $input);
        if ($changes === []) return $this->validationError(['product' => 'No editable fields were provided.']);
        if (! $products->skipValidation(true)->update($id, $changes)) {
            return $this->validationError($products->errors());
        }
        if (isset($input['expected_stock_quantity']) && db_connect()->affectedRows() === 0
            && (int) $products->find($id)['stock_quantity'] !== (int) $input['expected_stock_quantity']) {
            return $this->error('Stock changed. Reload the product before saving.', 409);
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

        if (! $products->skipValidation(true)->update((int) $product['id'], ['is_active' => 0])) {
            return $this->error('Unable to archive product.', 503);
        }

        return $this->success(null, 'Product archived. Historical orders are preserved.');
    }

    public function uploadImage(string $uid)
    {
        $products = new ProductModel();
        $product = $products->findByUid($uid);
        if (! $product) return $this->error('Product not found', 404);
        $file = $this->request->getFile('image');
        if (! $file) return $this->validationError(['image' => 'Choose an image.']);
        $images = new ProductImageService();
        $path = null;
        try {
            $path = $images->store($file);
            if (! $products->skipValidation(true)->update($product['id'], ['image' => $path])) throw new \RuntimeException('Image update failed');
            // Old files are retained to avoid breaking cached pages or another concurrent upload.
            return $this->success(['product' => $this->publicProduct($products->find($product['id']))], 'Image updated');
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['image' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $images->remove($path);
            log_message('error', 'Product image upload failed: {type}', ['type' => get_class($exception)]);
            return $this->error('Unable to save image.', 503);
        }
    }

    public function removeImage(string $uid)
    {
        $products = new ProductModel();
        $product = $products->findByUid($uid);
        if (! $product) return $this->error('Product not found', 404);
        if (! $products->skipValidation(true)->update($product['id'], ['image' => null])) return $this->error('Unable to remove image.', 503);
        return $this->success(['product' => $this->publicProduct($products->find($product['id']))], 'Image removed');
    }
}
