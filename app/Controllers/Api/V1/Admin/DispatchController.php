<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\DispatchModel;
use App\Services\DispatchService;
use App\Services\OrderConflictException;

class DispatchController extends BaseApiController
{
    public function index() { return $this->listing(false); }
    public function candidates() { return $this->listing(true); }
    private function listing(bool $candidates)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $query = $this->request->getGet();
        $rules = ['page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]', 'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]', 'search' => 'permit_empty|max_length[120]'];
        if (! $candidates) $rules += ['status' => 'permit_empty|in_list[ready_for_delivery,out_for_delivery]', 'assignment' => 'permit_empty|in_list[assigned,unassigned]'];
        if (! $this->validateData($query, $rules)) return $this->validationError($this->validator->getErrors());
        $query['page'] = (int) ($query['page'] ?? 1) ?: 1; $query['per_page'] = (int) ($query['per_page'] ?? 20) ?: 20;
        try { return $this->success($candidates ? (new DispatchModel())->candidates($query) : (new DispatchService())->queue($query)); }
        catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    public function assign(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $data = $this->requestData();
        if (array_diff(array_keys($data), ['rider_uid', 'expected_assignment_uid']) !== []) return $this->validationError(['assignment' => 'Only rider and observed assignment can be supplied.']);
        foreach (['rider_uid', 'expected_assignment_uid'] as $field) if (isset($data[$field]) && ! is_string($data[$field])) return $this->validationError([$field => 'Must be a UID string.']);
        if (! $this->validateData($data, ['rider_uid' => 'required|max_length[40]', 'expected_assignment_uid' => 'required|max_length[40]'])) return $this->validationError($this->validator->getErrors());
        try { return $this->success((new DispatchService())->assign($uid, $data['rider_uid'], $data['expected_assignment_uid'], (int) session('user_id')), 'Delivery assignment saved'); }
        catch (\OutOfBoundsException $exception) { return $this->error($exception->getMessage(), 404); }
        catch (OrderConflictException $exception) { return $this->error($exception->getMessage(), 409); }
        catch (\DomainException $exception) { return $this->error($exception->getMessage(), 403); }
        catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    private function unavailable(\Throwable $exception) {
        log_message('error', 'Dispatch request failed: {type}', ['type' => $exception::class]);
        return $this->error('Dispatch is temporarily unavailable. Please try again.', 503);
    }
}
