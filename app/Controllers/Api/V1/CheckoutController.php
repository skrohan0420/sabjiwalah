<?php

namespace App\Controllers\Api\V1;

use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PricingService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

class CheckoutController extends BaseApiController
{
    private const DELIVERY_CHARGE = 40.00;
    private const FREE_DELIVERY_MINIMUM = 499.00;
    private const OTP_TTL_SECONDS = 600;

    public function summary()
    {
        return $this->success([
            'checkout' => $this->checkoutPayload(),
        ]);
    }

    public function startOtp()
    {
        $data = $this->requestData();
        $rules = [
            'customer_phone' => 'required|max_length[30]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $phone = $this->normalizePhone((string) $data['customer_phone']);
        $code = (string) random_int(100000, 999999);
        $expiresAt = time() + self::OTP_TTL_SECONDS;

        session()->set('checkout_otp', [
            'phone'      => $phone,
            'code'       => $code,
            'verified'   => false,
            'expires_at' => $expiresAt,
        ]);

        return $this->success([
            'dev_otp'    => $code,
            'expires_at' => date(DATE_ATOM, $expiresAt),
        ], 'OTP generated for checkout testing.');
    }

    public function verifyOtp()
    {
        $data = $this->requestData();
        $rules = [
            'customer_phone' => 'required|max_length[30]',
            'otp'            => 'required|numeric|exact_length[6]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $otp = session('checkout_otp');

        if (! is_array($otp)) {
            return $this->error('Please request an OTP first.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        if (time() > (int) $otp['expires_at']) {
            session()->remove('checkout_otp');

            return $this->error('OTP expired. Please request a new OTP.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        if (
            $this->normalizePhone((string) $data['customer_phone']) !== $otp['phone']
            || (string) $data['otp'] !== $otp['code']
        ) {
            return $this->error('Invalid OTP.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $otp['verified'] = true;
        session()->set('checkout_otp', $otp);

        return $this->success([
            'verified' => true,
        ], 'Phone verified for checkout.');
    }

    public function place()
    {
        $data = $this->requestData();
        $rules = [
            'customer_name'  => 'required|max_length[120]',
            'customer_phone' => 'required|max_length[30]',
            'address_line'   => 'required|max_length[500]',
            'city'           => 'required|max_length[120]',
            'state'          => 'permit_empty|max_length[120]',
            'postal_code'    => 'required|max_length[20]',
            'notes'          => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        if (! $this->hasVerifiedOtpFor((string) $data['customer_phone'])) {
            return $this->error('Please verify the checkout phone number before placing the order.', ResponseInterface::HTTP_FORBIDDEN);
        }

        try {
            $order = (new OrderService())->createFromCart((int) session('user_id'), $data);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        session()->remove('checkout_otp');

        return $this->success([
            'order' => $this->publicOrder($order),
        ], 'Order placed successfully', ResponseInterface::HTTP_CREATED);
    }

    private function checkoutPayload(): array
    {
        $cart = new CartService();
        $pricing = (new PricingService())->calculateCart($cart->items());
        $subtotal = (float) $pricing['subtotal'];
        $discountAmount = 0.00;
        $deliveryCharge = $subtotal >= self::FREE_DELIVERY_MINIMUM || $subtotal <= 0 ? 0.00 : self::DELIVERY_CHARGE;

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
            'count'                 => $cart->count(),
            'subtotal'              => $subtotal,
            'discount_amount'       => $discountAmount,
            'delivery_charge'       => $deliveryCharge,
            'total_amount'          => $subtotal - $discountAmount + $deliveryCharge,
            'free_delivery_minimum' => self::FREE_DELIVERY_MINIMUM,
        ];
    }

    private function hasVerifiedOtpFor(string $phone): bool
    {
        $otp = session('checkout_otp');

        return is_array($otp)
            && (bool) ($otp['verified'] ?? false)
            && time() <= (int) ($otp['expires_at'] ?? 0)
            && $this->normalizePhone($phone) === ($otp['phone'] ?? null);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        $digits = ltrim($digits, '0');

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }
}
