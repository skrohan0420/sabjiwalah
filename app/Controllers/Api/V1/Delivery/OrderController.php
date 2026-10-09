<?php

namespace App\Controllers\Api\V1\Delivery;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\DeliveryAssignmentModel;
use App\Models\OrderModel;
use App\Services\OrderService;
use CodeIgniter\HTTP\ResponseInterface;

class OrderController extends BaseApiController
{
    public function index()
    {
        $deliveryUserId = (int) session('user_id');
        $assignmentRows = (new DeliveryAssignmentModel())
            ->where('delivery_user_id', $deliveryUserId)
            ->findAll();
        $orderIds = array_column($assignmentRows, 'order_id');

        if ($orderIds === []) {
            return $this->success(['items' => []]);
        }

        $orders = (new OrderModel())
            ->whereIn('id', $orderIds)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return $this->success([
            'items' => array_map(fn (array $order): array => $this->publicOrder($order), $orders),
        ]);
    }

    public function show(string $uid)
    {
        $order = (new OrderModel())->findByUid($uid);

        if (! $order || ! $this->isAssignedToCurrentDeliveryUser((int) $order['id'])) {
            return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->success([
            'order' => $this->publicOrder($order),
        ]);
    }

    public function updateStatus(string $uid)
    {
        $orders = new OrderModel();
        $order = $orders->findByUid($uid);

        if (! $order || ! $this->isAssignedToCurrentDeliveryUser((int) $order['id'])) {
            return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $data = $this->requestData();
        $rules = [
            'status' => 'required|in_list[out_for_delivery,delivered,delivery_failed]',
            'notes'  => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        try {
            (new OrderService())->changeStatus(
                (int) $order['id'],
                (string) $data['status'],
                (int) session('user_id'),
                $data['notes'] ?? null,
            );
        } catch (\App\Services\OrderConflictException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            log_message('error', 'Delivery order status update failed: {type}', ['type' => $exception::class]);
            return $this->error('Unable to update this order. Please try again.', ResponseInterface::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->success([
            'order' => $this->publicOrder($orders->find((int) $order['id'])),
        ], 'Order status updated');
    }

    private function isAssignedToCurrentDeliveryUser(int $orderId): bool
    {
        return (new DeliveryAssignmentModel())
            ->where('order_id', $orderId)
            ->where('delivery_user_id', (int) session('user_id'))
            ->first() !== null;
    }
}
