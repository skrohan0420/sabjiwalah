<?php

namespace App\Controllers\Api\V1;

use App\Services\DeliveryPinCooldownException;
use App\Services\DeliveryPinService;
use App\Services\OrderConflictException;

class DeliveryPinController extends BaseApiController
{
    public function create(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0')->setHeader('Pragma', 'no-cache');
        if ($this->requestData() !== []) return $this->validationError(['payload' => 'No customer, PIN, or expiry fields are accepted.']);
        try {
            return $this->success((new DeliveryPinService())->issue($uid, (int) session('user_id')),
                'Delivery PIN generated. It is shown only on this screen.');
        } catch (\OutOfBoundsException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (DeliveryPinCooldownException $exception) {
            $this->response->setHeader('Retry-After', (string) $exception->retryAfter);
            return $this->error($exception->getMessage(), 429, null, ['retry_after' => $exception->retryAfter]);
        } catch (OrderConflictException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (\Throwable) {
            // Never log the request/response containing a plaintext PIN.
            return $this->error('Delivery PIN is temporarily unavailable. Try again later.', 503);
        }
    }
}
