<?php
namespace App\Controllers\Api\V1\Admin;
use App\Controllers\Api\V1\BaseApiController;
use App\Models\DeliveryCompletionModel;
use App\Services\DeliveryCompletionService;
class CashController extends BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store'); $query=$this->request->getGet();
        if (!$this->validateData($query,['page'=>'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]','per_page'=>'permit_empty|is_natural_no_zero|less_than_equal_to[100]','status'=>'permit_empty|in_list[pending,reconciled]','search'=>'permit_empty|max_length[120]'])) return $this->validationError($this->validator->getErrors());
        $query['page']=(int)($query['page']??1)?:1; $query['per_page']=(int)($query['per_page']??20)?:20;
        try {
            $data=(new DeliveryCompletionModel())->cashListing($query);
            $timestamps=new \App\Services\AdminOrderService();
            foreach($data['items'] as &$row) foreach(['collected_at','verified_at','reconciled_at'] as $field) $row[$field]=$timestamps->timestamp($row[$field]);
            unset($row); return $this->success($data);
        } catch (\Throwable) { return $this->error('Cash records are temporarily unavailable.',503); }
    }
    public function reconcile(string $uid)
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        if ($this->requestData()!==[]) return $this->validationError(['payload'=>'No amount or actor fields are accepted.']);
        try { (new DeliveryCompletionService())->reconcile($uid,(int)session('user_id')); return $this->success(null,'Cash reconciled.');
        } catch (\OutOfBoundsException $e) { return $this->error($e->getMessage(),404);
        } catch (\Throwable) { return $this->error('Unable to reconcile cash. Reload before trying again.',503); }
    }
}
