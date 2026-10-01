<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected function success(mixed $data = null, ?string $message = null, int $status = ResponseInterface::HTTP_OK)
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => true,
                'data'    => $data,
                'message' => $message,
            ]);
    }

    protected function error(
        string $message,
        int $status = ResponseInterface::HTTP_BAD_REQUEST,
        mixed $errors = null,
        mixed $data = null
    ) {
        $payload = [
            'success' => false,
            'data'    => $data,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }

    protected function validationError(array $errors)
    {
        return $this->error('Validation failed', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    protected function requestData(): array
    {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return $json;
        }

        $raw = $this->request->getRawInput();

        if ($raw !== []) {
            return $raw;
        }

        return $this->request->getPost() ?? [];
    }

    protected function publicUser(?array $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'     => (int) $user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'phone'  => $user['phone'],
            'role'   => $user['role'],
            'status' => $user['status'],
        ];
    }

    protected function publicProduct(array $product): array
    {
        return [
            'id'             => (int) $product['id'],
            'name'           => $product['name'],
            'slug'           => $product['slug'],
            'description'    => $product['description'],
            'image'          => $product['image'],
            'price'          => (float) $product['price'],
            'sale_price'     => $product['sale_price'] === null ? null : (float) $product['sale_price'],
            'unit'           => $product['unit'],
            'stock_quantity' => (int) $product['stock_quantity'],
            'is_active'      => (bool) $product['is_active'],
        ];
    }
}
