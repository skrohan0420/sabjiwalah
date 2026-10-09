<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\AdminCustomerService;

class CustomerController extends BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $query = $this->request->getGet();
        if (! $this->validateData($query, array_merge($this->paginationRules(), [
            'search' => 'permit_empty|max_length[120]', 'status' => 'permit_empty|in_list[active,inactive]',
            'sort' => 'permit_empty|in_list[name,created_at]', 'dir' => 'permit_empty|in_list[asc,desc]',
        ]))) return $this->validationError($this->validator->getErrors());
        [$query['page'], $query['per_page']] = $this->pagination($query);
        try { return $this->success((new AdminCustomerService())->listing($query)); }
        catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    public function show(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $query = $this->request->getGet();
        if (! $this->validateData($query, $this->paginationRules())) return $this->validationError($this->validator->getErrors());
        [$page, $perPage] = $this->pagination($query);
        try {
            $data = (new AdminCustomerService())->details($uid, $page, $perPage);
            return $data ? $this->success($data) : $this->error('Customer not found', 404);
        } catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    public function updateStatus(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $data = $this->requestData();
        if (array_diff(array_keys($data), ['status', 'expected_status']) !== []) return $this->validationError(['customer' => 'Only account status can be changed.']);
        if (! $this->validateData($data, ['status' => 'required|in_list[active,inactive]', 'expected_status' => 'required|in_list[active,inactive]'])) return $this->validationError($this->validator->getErrors());
        try {
            $result = (new AdminCustomerService())->changeStatus($uid, $data['status'], $data['expected_status']);
            if ($result === 'missing') return $this->error('Customer not found', 404);
            if ($result === 'conflict') return $this->error('Account status changed. Reload the customer before applying an action.', 409);
            return $this->success(['uid' => $uid, 'status' => $data['status']], $result === 'unchanged' ? 'Account status unchanged' : 'Account status updated');
        } catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    private function paginationRules(): array { return ['page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]', 'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]']; }
    private function pagination(array $query): array { return [(int) ($query['page'] ?? 1) ?: 1, (int) ($query['per_page'] ?? 20) ?: 20]; }
    private function unavailable(\Throwable $exception) {
        log_message('error', 'Admin customer request failed: {type}', ['type' => get_class($exception)]);
        return $this->error('Unable to complete the customer request. Please try again.', 503);
    }
}
