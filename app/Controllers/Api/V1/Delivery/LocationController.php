<?php

namespace App\Controllers\Api\V1\Delivery;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\DeliveryLocationService;

class LocationController extends BaseApiController
{
    public function create()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $data = $this->requestData();
        if (!DeliveryLocationService::validSample($data) || !$this->validateData($data, [
            'order_uid' => 'required|max_length[80]', 'assignment_uid' => 'required|max_length[80]',
            'latitude' => 'required|decimal|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'longitude' => 'required|decimal|greater_than_equal_to[-180]|less_than_equal_to[180]',
            'accuracy' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'captured_at_ms' => 'required|integer',
        ])) return $this->validationError(['location' => 'Supply fresh numeric coordinates and accuracy within 100 metres for this assignment.']);
        return $this->perform(fn () => (new DeliveryLocationService())->record((int) session('user_id'), $data));
    }

    public function stop()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if ($this->requestData() !== []) return $this->validationError(['location' => 'No rider identifier may be supplied.']);
        return $this->perform(function () { (new DeliveryLocationService())->stop((int) session('user_id')); return null; });
    }

    private function perform(callable $action)
    {
        try { return $this->success($action()); }
        catch (\OutOfBoundsException) { return $this->error('Order not found.', 404); }
        catch (\App\Services\OrderConflictException $e) { return $this->error($e->getMessage(), 409); }
        catch (\App\Services\DeliveryLocationRateLimitException) { $this->response->setHeader('Retry-After', '15'); return $this->error('Wait 15 seconds before sending another location.', 429); }
        catch (\InvalidArgumentException $e) { return $this->validationError(['location' => $e->getMessage()]); }
        catch (\Throwable) { return $this->error('Location service unavailable. Tracking has stopped; refresh before enabling it again.', 503); }
    }
}
