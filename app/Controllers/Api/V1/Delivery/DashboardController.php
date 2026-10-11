<?php

namespace App\Controllers\Api\V1\Delivery;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\DeliveryDashboardService;

class DashboardController extends BaseApiController
{
    public function updateAvailability()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $data = $this->requestData();
        if ($this->request->getGet() !== [] || array_diff(array_keys($data), ['availability', 'expected_availability']) !== []) return $this->validationError(['availability' => 'Only your availability and its previous value may be supplied.']);
        foreach (['availability', 'expected_availability'] as $key) if (isset($data[$key]) && ! is_string($data[$key])) return $this->validationError([$key => 'Must be text.']);
        if (! $this->validateData($data, ['availability' => 'required|in_list[available,unavailable,offline]', 'expected_availability' => 'required|in_list[available,unavailable,offline]'])) return $this->validationError($this->validator->getErrors());
        try {
            $result = (new \App\Services\AdminDeliveryPersonnelService())->changeOwnAvailability((int) session('user_id'), $data['availability'], $data['expected_availability']);
            if (in_array($result, ['missing', 'inactive'], true)) return $this->error('Active delivery account required.', 403);
            if ($result === 'assigned') return $this->error('Finish your active assignments before changing availability.', 409);
            if ($result === 'conflict') return $this->error('Availability changed. Refresh your dashboard before trying again.', 409);
            return $this->success(['base_availability' => $data['availability']], 'Availability updated.');
        } catch (\Throwable $error) {
            log_message('error', 'Delivery availability failed: {type}', ['type' => $error::class]);
            return $this->error('Unable to update availability. Refresh before trying again.', 503);
        }
    }

    public function show()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if ($this->request->getGet() !== []) return $this->validationError(['query' => 'The dashboard always uses your signed-in account.']);
        try { return $this->success((new DeliveryDashboardService())->snapshot((int) session('user_id'))); }
        catch (\OutOfBoundsException) { return $this->error('Active delivery account required.', 403); }
        catch (\Throwable $error) {
            log_message('error', 'Delivery dashboard failed: {type}', ['type' => $error::class]);
            return $this->error('Unable to load your dashboard. Please try again.', 503);
        }
    }
}
