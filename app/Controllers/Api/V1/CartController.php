<?php

namespace App\Controllers\Api\V1;

use App\Models\ProductModel;
use App\Services\CartService;
use App\Services\PricingService;
use CodeIgniter\HTTP\ResponseInterface;

class CartController extends BaseApiController
{
    public function show()
    {
        return $this->success([
            'cart' => $this->cartPayload(),
        ]);
    }

    public function addItem()
    {
        $data = $this->requestData();
        $rules = [
            'product_uid' => 'required|max_length[40]',
            'quantity'    => 'permit_empty|is_natural_no_zero|less_than_equal_to[999]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $product = $this->findActiveProduct((string) $data['product_uid']);

        if (! $product) {
            return $this->error('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        if ($quantity > (int) $product['stock_quantity']) {
            return $this->error('Requested quantity is not available', ResponseInterface::HTTP_CONFLICT);
        }

        (new CartService())->add((string) $product['uid'], $quantity);

        return $this->success([
            'cart' => $this->cartPayload(),
        ], 'Product added to cart', ResponseInterface::HTTP_CREATED);
    }

    public function updateItem(string $productUid)
    {
        $data = $this->requestData();
        $rules = [
            'quantity' => 'required|is_natural|less_than_equal_to[999]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $quantity = (int) $data['quantity'];
        $product = $this->findActiveProduct($productUid);

        if (! $product) {
            return $this->error('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        if ($quantity > (int) $product['stock_quantity']) {
            return $this->error('Requested quantity is not available', ResponseInterface::HTTP_CONFLICT);
        }

        (new CartService())->update($productUid, $quantity);

        return $this->success([
            'cart' => $this->cartPayload(),
        ], $quantity === 0 ? 'Product removed from cart' : 'Cart updated');
    }

    public function removeItem(string $productUid)
    {
        (new CartService())->remove($productUid);

        return $this->success([
            'cart' => $this->cartPayload(),
        ], 'Product removed from cart');
    }

    public function clear()
    {
        (new CartService())->clear();

        return $this->success([
            'cart' => $this->cartPayload(),
        ], 'Cart cleared');
    }

    private function cartPayload(): array
    {
        $cart = new CartService();
        $pricing = (new PricingService())->calculateCart($cart->items());

        return [
            'items' => array_map(
                fn (array $line): array => [
                    'product'    => $this->publicProduct($line['product']),
                    'quantity'   => (int) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'total'      => (float) $line['total'],
                ],
                $pricing['lines'],
            ),
            'count'    => $cart->count(),
            'subtotal' => (float) $pricing['subtotal'],
        ];
    }

    private function findActiveProduct(string $productUid): ?array
    {
        $product = (new ProductModel())->findByUid($productUid);

        if (! $product || ! (bool) $product['is_active']) {
            return null;
        }

        return $product;
    }
}
