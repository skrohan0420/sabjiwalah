<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected function otpResponse(callable $action)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        try { return $action(); }
        catch (\App\Services\OtpRateLimitException $e) {
            $this->response->setHeader('Retry-After', (string) $e->retryAfter);
            return $this->error($e->getMessage() . ' Try again in ' . $e->retryAfter . ' seconds.', 429, null, ['retry_after' => $e->retryAfter]);
        }
        catch (\App\Services\OtpUnavailableException $e) { return $this->error($e->getMessage(), 503); }
        catch (\InvalidArgumentException $e) { return $this->validationError(['phone_or_otp' => $e->getMessage()]); }
        catch (\Throwable) { return $this->error('Unable to complete phone verification. Please try again later.', 503); }
    }

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
            'uid'    => $user['uid'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'phone'  => $user['phone'],
            'role'   => $user['role'],
            'status' => $user['status'],
            'profile_complete' => \App\Services\CustomerSetup::complete($user),
        ];
    }

    protected function publicProduct(array $product): array
    {
        return [
            'uid'            => $product['uid'],
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

    protected function publicOrder(array $order): array
    {
        return [
            'uid'             => $order['uid'],
            'order_number'    => $order['order_number'],
            'subtotal'        => (float) $order['subtotal'],
            'discount_amount' => (float) $order['discount_amount'],
            'delivery_charge' => (float) $order['delivery_charge'],
            'total_amount'    => (float) $order['total_amount'],
            'payment_method'  => $order['payment_method'],
            'payment_status'  => $order['payment_status'],
            'order_status'    => $order['order_status'],
            'customer_name'   => $order['customer_name'],
            'customer_phone'  => $order['customer_phone'],
            'address_line'    => $order['address_line'],
            'city'            => $order['city'],
            'state'           => $order['state'],
            'postal_code'     => $order['postal_code'],
            'delivery_latitude' => isset($order['delivery_latitude']) ? (float) $order['delivery_latitude'] : null,
            'delivery_longitude' => isset($order['delivery_longitude']) ? (float) $order['delivery_longitude'] : null,
            'notes'           => $order['notes'],
            'created_at'      => $order['created_at'],
            'updated_at'      => $order['updated_at'],
        ];
    }
}
