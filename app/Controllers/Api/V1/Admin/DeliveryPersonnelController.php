<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\AdminDeliveryPersonnelService;

class DeliveryPersonnelController extends BaseApiController
{
    public function index()
    {
        $this->privateResponse();
        $query = $this->request->getGet();
        if (! $this->validateData($query, $this->paginationRules() + ['search' => 'permit_empty|max_length[120]', 'status' => 'permit_empty|in_list[active,inactive]'])) return $this->validationError($this->validator->getErrors());
        $query['page'] = (int) ($query['page'] ?? 1) ?: 1;
        $query['per_page'] = (int) ($query['per_page'] ?? 20) ?: 20;
        try { return $this->success((new AdminDeliveryPersonnelService())->listing($query)); }
        catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    public function show(string $uid)
    {
        $this->privateResponse();
        $query = $this->request->getGet();
        if (! $this->validateData($query, $this->paginationRules() + ['mode' => 'permit_empty|in_list[current,history]'])) return $this->validationError($this->validator->getErrors());
        try {
            $data = (new AdminDeliveryPersonnelService())->details($uid, (int) ($query['page'] ?? 1) ?: 1, (int) ($query['per_page'] ?? 20) ?: 20, ($query['mode'] ?? '') ?: 'current');
            return $data ? $this->success($data) : $this->error('Delivery account not found', 404);
        } catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    public function create()
    {
        $this->privateResponse();
        $data = $this->requestData();
        if (array_diff(array_keys($data), ['name', 'phone', 'email']) !== []) return $this->validationError(['account' => 'Only name, phone and email can be supplied.']);
        foreach (['name', 'phone', 'email'] as $field) {
            if (isset($data[$field]) && ! is_string($data[$field])) return $this->validationError([$field => 'Must be text.']);
            $data[$field] = trim($data[$field] ?? '');
        }
        if (! preg_match('/^[+0-9 ()-]+$/', $data['phone'])) return $this->validationError(['phone' => 'Enter a valid 10-digit Indian mobile number.']);
        $digits = preg_replace('/\D+/', '', $data['phone']);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
        $data['phone'] = $digits; $data['email'] = strtolower($data['email']);
        if (! $this->validateData($data, [
            'name' => 'required|max_length[120]', 'phone' => 'required|regex_match[/^[6-9][0-9]{9}$/]|is_unique[users.phone]',
            'email' => 'permit_empty|valid_email|max_length[190]|is_unique[users.email]',
        ])) return $this->validationError($this->validator->getErrors());
        try { return $this->success(['uid' => (new AdminDeliveryPersonnelService())->create($data), 'status' => 'inactive'], 'Delivery account created inactive. Review and activate it before login.', 201); }
        catch (\Throwable $exception) {
            // A unique-key race must not overwrite or convert an existing customer/admin account.
            if (str_contains($exception->getMessage(), 'Duplicate entry')) return $this->error('Phone or email already belongs to an account. Reload before trying again.', 409);
            return $this->unavailable($exception);
        }
    }

    public function updateStatus(string $uid) { return $this->change($uid, 'status', 'active,inactive'); }
    public function updateAvailability(string $uid) { return $this->change($uid, 'availability', 'offline,available,unavailable'); }

    private function change(string $uid, string $field, string $values)
    {
        $this->privateResponse();
        $data = $this->requestData(); $expected = 'expected_' . $field;
        if (array_diff(array_keys($data), [$field, $expected]) !== []) return $this->validationError(['account' => 'Only the selected account control can be changed.']);
        if (! $this->validateData($data, [$field => 'required|in_list[' . $values . ']', $expected => 'required|in_list[' . $values . ']'])) return $this->validationError($this->validator->getErrors());
        try {
            $result = (new AdminDeliveryPersonnelService())->change($uid, $field, $data[$field], $data[$expected]);
            if ($result === 'missing') return $this->error('Delivery account not found', 404);
            if ($result === 'conflict') return $this->error('Account changed. Reload details before applying an action.', 409);
            if ($result === 'inactive') return $this->error('Activate the account before setting availability.', 409);
            return $this->success(['uid' => $uid, $field => $data[$field]], 'Delivery account updated');
        } catch (\Throwable $exception) { return $this->unavailable($exception); }
    }

    private function privateResponse(): void { $this->response->setHeader('Cache-Control', 'no-store, private'); }
    private function paginationRules(): array { return ['page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]', 'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]']; }
    private function unavailable(\Throwable $exception) {
        log_message('error', 'Admin delivery personnel request failed: {type}', ['type' => $exception::class]);
        return $this->error('Unable to complete the delivery personnel request. Please try again.', 503);
    }
}
