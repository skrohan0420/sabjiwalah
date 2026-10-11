<?php

namespace App\Controllers\Api\V1\Delivery;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\DeliveryOrderModel;
use App\Services\DeliveryOrderService;
use CodeIgniter\HTTP\ResponseInterface;

class OrderController extends BaseApiController
{
    public function complete(string $uid)
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        $data=$this->requestData();
        if (array_diff(array_keys($data),['pin','cash_received']) || !$this->validateData($data,['pin'=>'required|regex_match[/^[0-9]{6}$/]']) || !isset($data['cash_received']) || !is_bool($data['cash_received'])) return $this->validationError(['completion'=>'Enter the six-digit customer PIN and a boolean cash confirmation.']);
        try {
            (new \App\Services\DeliveryCompletionService())->complete($uid,(int)session('user_id'),(string)$data['pin'],$data['cash_received']);
            return $this->success(null,'Delivery completed.');
        } catch (\OutOfBoundsException $e) { return $this->error('Order not found.',404);
        } catch (\App\Services\DeliveryVerificationException $e) { return $this->error($e->getMessage(),$e->httpStatus);
        } catch (\App\Services\OrderConflictException $e) { return $this->error($e->getMessage(),409);
        } catch (\Throwable) { return $this->error('Unable to confirm delivery. Reload the order before trying again.',503); }
    }
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $orders = (new DeliveryOrderModel())->currentForRider((int) session('user_id'), null, true);

        return $this->success([
            'items' => array_map(fn (array $order): array => $this->riderOrder($order), $orders),
        ]);
    }

    public function show(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $order = (new DeliveryOrderModel())->currentForRider((int) session('user_id'), $uid)[0] ?? null;

        if (! $order) {
            return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $items = (new \App\Models\OrderItemModel())->select('product_name, unit, quantity, unit_price, total_price')->where('order_id', $order['id'])->orderBy('id', 'ASC')->findAll();
        $history = (new \App\Models\OrderStatusHistoryModel())->select('new_status, created_at')->where('order_id', $order['id'])->orderBy('id', 'ASC')->findAll();
        foreach ($history as &$entry) { $entry['occurred_at'] = $this->timestamp($entry['created_at']); unset($entry['created_at']); }
        unset($entry);
        return $this->success(['order' => $this->riderOrder($order), 'items' => $items, 'history' => $history]);
    }

    public function updateStatus(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $order = (new DeliveryOrderModel())->currentForRider((int) session('user_id'), $uid)[0] ?? null;

        if (! $order) {
            return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $data = $this->requestData();
        if (array_diff(array_keys($data), ['status', 'notes', 'expected_status'])) return $this->validationError(['order' => 'Only status, notes and expected status may be supplied.']);
        foreach (['status', 'notes', 'expected_status'] as $field) if (isset($data[$field]) && ! is_string($data[$field])) return $this->validationError([$field => 'Must be text.']);
        $rules = [
            'status' => 'required|in_list[out_for_delivery,delivered,delivery_failed]',
            'notes'  => 'permit_empty|max_length[1000]',
            'expected_status' => 'permit_empty|in_list[ready_for_delivery,out_for_delivery]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        try {
            (new DeliveryOrderService())->changeStatus(
                $uid,
                (string) $data['status'],
                (int) session('user_id'),
                $data['notes'] ?? null,
                ($data['expected_status'] ?? '') ?: null,
            );
        } catch (\OutOfBoundsException $exception) {
            return $this->error('Order not found', ResponseInterface::HTTP_NOT_FOUND);
        } catch (\App\Services\OrderConflictException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            log_message('error', 'Delivery order status update failed: {type}', ['type' => $exception::class]);
            return $this->error('Unable to update this order. Please try again.', ResponseInterface::HTTP_SERVICE_UNAVAILABLE);
        }

        $current = (new DeliveryOrderModel())->currentForRider((int) session('user_id'), $uid)[0] ?? null;
        return $this->success(['order' => $current ? $this->riderOrder($current) : null], 'Order status updated');
    }

    private function riderOrder(array $order): array
    {
        $result = $this->publicOrder($order);
        $result['assignment_uid'] = $order['assignment_uid'] ?? null;
        foreach (['created_at', 'updated_at', 'assigned_at', 'completed_at'] as $key) $result[$key] = $this->timestamp($order[$key] ?? null);
        return $result;
    }

    private function timestamp(?string $value): ?string
    {
        return $value ? (new \DateTimeImmutable($value, new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
    }

}
