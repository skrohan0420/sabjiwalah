<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\OrderModel;
use App\Services\AdminOrderService;
use App\Services\OrderConflictException;
use App\Services\OrderService;
use CodeIgniter\HTTP\ResponseInterface;

class OrderController extends BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $query = $this->request->getGet();
        $rules = [
            'page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'status' => 'permit_empty|in_list[pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled,delivery_failed]',
            'search' => 'permit_empty|max_length[120]',
            'from' => 'permit_empty|valid_date[Y-m-d]',
            'to' => 'permit_empty|valid_date[Y-m-d]',
            'sort' => 'permit_empty|in_list[created_at,order_number,total_amount]',
            'dir' => 'permit_empty|in_list[asc,desc]',
        ];
        if (! $this->validateData($query, $rules)) return $this->validationError($this->validator->getErrors());
        if (! empty($query['from']) && ! empty($query['to']) && $query['from'] > $query['to']) {
            return $this->validationError(['to' => 'End date must be on or after the start date.']);
        }
        $query['page'] = (int) ($query['page'] ?? 1) ?: 1;
        $query['per_page'] = (int) ($query['per_page'] ?? 20) ?: 20;
        try {
            $service = new AdminOrderService();
            $data = $service->listing($query);
            $data['items'] = array_map(fn (array $order): array => array_merge($this->publicOrder($order), [
                'item_count' => $order['item_count'],
                'assigned_delivery_name' => $order['assigned_delivery_name'],
                'placed_at' => $service->timestamp($order['created_at']),
            ]), $data['items']);
            return $this->success($data);
        } catch (\Throwable $exception) {
            return $this->unavailable($exception);
        }
    }

    public function show(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        try {
            $service = new AdminOrderService();
            $data = $service->details($uid);
            if (! $data) return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
            $data['order'] = array_merge($this->publicOrder($data['order']), ['placed_at' => $service->timestamp($data['order']['created_at'])]);
            return $this->success($data);
        } catch (\Throwable $exception) {
            return $this->unavailable($exception);
        }
    }

    public function updateStatus(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $data = $this->requestData();
        $rules = [
            'status' => 'required|in_list[pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled,delivery_failed]',
            'expected_status' => 'permit_empty|in_list[pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled,delivery_failed]',
            'notes' => 'permit_empty|max_length[1000]',
        ];
        if (! $this->validateData($data, $rules)) return $this->validationError($this->validator->getErrors());
        try {
            $orders = new OrderModel();
            $order = $orders->findByUid($uid);
            if (! $order) return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
            (new OrderService())->changeStatus((int) $order['id'], (string) $data['status'], (int) session('user_id'),
                $data['notes'] ?? null, ($data['expected_status'] ?? '') ?: null);
            return $this->success(['order' => $this->publicOrder($orders->find((int) $order['id']))], 'Order status updated');
        } catch (OrderConflictException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->unavailable($exception);
        }
    }

    private function unavailable(\Throwable $exception)
    {
        log_message('error', 'Admin orders request failed: {type}', ['type' => $exception::class]);
        return $this->error('Orders are temporarily unavailable. Please try again.', ResponseInterface::HTTP_SERVICE_UNAVAILABLE);
    }
}
