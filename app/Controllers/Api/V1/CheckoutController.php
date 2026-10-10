<?php

namespace App\Controllers\Api\V1;

use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PricingService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

class CheckoutController extends BaseApiController
{
    public function summary()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        try { return $this->success([
            'checkout' => $this->checkoutPayload(),
        ]); } catch (\Throwable) { return $this->error('Checkout totals are temporarily unavailable.', 503); }
    }

    public function applyOffer()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $data = $this->requestData();
        if (array_keys($data) !== ['code'] || !is_string($data['code']) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/D', trim($data['code'])))
            return $this->validationError(['code' => 'Enter only a valid coupon code.']);
        $code = strtoupper(trim($data['code']));
        try {
            $pricing = (new PricingService())->calculateCart((new CartService())->items());
            (new \App\Services\CheckoutSummaryService())->quote($pricing, $code);
            session()->set('checkout_offer', $code);
            return $this->success(['checkout' => $this->checkoutPayload()], 'Coupon applied.');
        } catch (InvalidArgumentException $e) { return $this->error($e->getMessage(), 422);
        } catch (\Throwable) { return $this->error('Coupons are temporarily unavailable.', 503); }
    }

    public function removeOffer()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if ($this->requestData() !== []) return $this->validationError(['payload' => 'No fields are accepted.']);
        try {
            session()->remove('checkout_offer');
            return $this->success(['checkout' => $this->checkoutPayload()], 'Coupon removed.');
        } catch (\Throwable) { return $this->error('Checkout totals are temporarily unavailable.', 503); }
    }

    public function startOtp()
    {
        return $this->otpResponse(function () {
            $data = $this->otpInput(false);
            return $this->success((new \App\Services\OtpService())->start('checkout', $data['customer_phone']), 'OTP generated for local development testing.');
        });
    }

    public function verifyOtp()
    {
        return $this->otpResponse(function () {
            $data = $this->otpInput(true);
            if (! (new \App\Services\OtpService())->verify('checkout', $data['customer_phone'], $data['otp'])) {
                return $this->error('Invalid or expired OTP. Please request a new code when the resend wait has ended.', 400);
            }
            return $this->success(['verified' => true], 'Phone verified for checkout.');
        });
    }

    private function otpInput(bool $verify): array
    {
        $data = $this->requestData();
        $allowed = $verify ? ['customer_phone', 'otp'] : ['customer_phone'];
        if (array_diff(array_keys($data), $allowed) || ! is_string($data['customer_phone'] ?? null)
            || ($verify && (! is_string($data['otp'] ?? null) || ! preg_match('/^\d{6}$/D', $data['otp'])))) {
            throw new InvalidArgumentException('Enter only a phone number and, when verifying, a six-digit OTP.');
        }
        $data['customer_phone'] = \App\Services\OtpService::normalizePhone($data['customer_phone']);
        return $data;
    }

    public function place()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $data = $this->requestData();
        $rules = [
            'customer_name'  => 'required|max_length[120]',
            'customer_phone' => 'required|max_length[30]',
            'address_line'   => 'required|max_length[500]',
            'city'           => 'required|max_length[120]',
            'state'          => 'permit_empty|max_length[120]',
            'postal_code'    => 'required|max_length[20]',
            'notes'          => 'permit_empty|max_length[1000]',
            'delivery_latitude' => 'permit_empty|numeric|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'delivery_longitude' => 'permit_empty|numeric|greater_than_equal_to[-180]|less_than_equal_to[180]',
            'quote_token' => 'permit_empty|regex_match[/^[a-f0-9]{64}$/D]',
        ];
        if (array_diff(array_keys($data), array_keys($rules))) return $this->validationError(['payload' => 'Unknown checkout fields. Prices and discounts are calculated by the server.']);
        foreach ($data as $key => $value) if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value)) return $this->validationError([$key => 'Use a single field value.']);

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        try {
            if (! (new \App\Services\OtpService())->verifiedForCheckout((string) $data['customer_phone'])) {
                return $this->error('Please verify the checkout phone number before placing the order.', ResponseInterface::HTTP_FORBIDDEN);
            }
        } catch (InvalidArgumentException $e) { return $this->validationError(['customer_phone' => $e->getMessage()]);
        } catch (\App\Services\OtpUnavailableException $e) { return $this->error($e->getMessage(), 503); }

        try {
            $order = (new OrderService())->createFromCart((int) session('user_id'), $data);
        } catch (\App\Services\OrderConflictException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        } catch (\Throwable) {
            return $this->error('Unable to confirm this order. Check your orders before trying again.', 503);
        }

        session()->remove('checkout_otp');

        return $this->success([
            'order' => $this->publicOrder($order),
        ], 'Order placed successfully', ResponseInterface::HTTP_CREATED);
    }

    private function checkoutPayload(): array
    {
        return (new \App\Services\CheckoutSummaryService())->summary();
    }

}
