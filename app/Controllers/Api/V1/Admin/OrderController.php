<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\OrderModel;
use App\Services\OrderService;
use CodeIgniter\HTTP\ResponseInterface;

class OrderController extends BaseApiController
{
    public function index()
    {
        $query = $this->request->getGet();
        $rules = [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'status'   => 'permit_empty|max_length[40]',
        ];

        if (! $this->validateData($query, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20)));
        $orders = new OrderModel();

        if (! empty($query['status'])) {
            $orders->where('order_status', (string) $query['status']);
        }

        $rows = $orders
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage, 'default', $page);

        return $this->success([
            'items' => $rows,
            'pager' => [
                'current_page' => $orders->pager->getCurrentPage(),
                'per_page'     => $perPage,
                'total'        => $orders->pager->getTotal(),
                'page_count'   => $orders->pager->getPageCount(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $order = (new OrderModel())->find($id);

        if (! $order) {
            return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->success(['order' => $order]);
    }

    public function updateStatus(int $id)
    {
        $data = $this->requestData();
        $rules = [
            'status' => 'required|in_list[pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled,delivery_failed]',
            'notes'  => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        try {
            (new OrderService())->changeStatus(
                $id,
                (string) $data['status'],
                (int) session('user_id'),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        return $this->success([
            'order' => (new OrderModel())->find($id),
        ], 'Order status updated');
    }
}
