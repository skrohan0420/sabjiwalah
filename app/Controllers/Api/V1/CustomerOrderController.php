<?php

namespace App\Controllers\Api\V1;

class CustomerOrderController extends BaseApiController
{
    public function show(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0')->setHeader('Pragma', 'no-cache');
        try { return $this->success((new \App\Services\CustomerOrderService())->detail($uid, (int) session('user_id'))); }
        catch (\OutOfBoundsException $exception) { return $this->error($exception->getMessage(), 404); }
        catch (\Throwable) { return $this->error('Order tracking is temporarily unavailable.', 503); }
    }
}
