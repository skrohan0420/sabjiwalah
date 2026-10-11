<?php

namespace App\Controllers\Api\V1\Delivery;

class HistoryController extends \App\Controllers\Api\V1\BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store');$query=$this->request->getGet();
        $rules=['page'=>'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]','per_page'=>'permit_empty|is_natural_no_zero|less_than_equal_to[50]',
            'status'=>'permit_empty|in_list[delivered,delivery_failed]','search'=>'permit_empty|max_length[120]','from'=>'permit_empty|regex_match[/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/]|valid_date[Y-m-d]','to'=>'permit_empty|regex_match[/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/]|valid_date[Y-m-d]'];
        if(array_diff(array_keys($query),array_keys($rules)))return $this->validationError(['query'=>'Only history filters are accepted.']);
        foreach($query as $value)if(!is_string($value))return $this->validationError(['query'=>'Filters must be text.']);
        if(!$this->validateData($query,$rules))return $this->validationError($this->validator->getErrors());
        if(!empty($query['from'])&&!empty($query['to'])&&$query['from']>$query['to'])return $this->validationError(['to'=>'End date must be on or after start date.']);
        $query['page']=(int)($query['page']??1)?:1;$query['per_page']=(int)($query['per_page']??20)?:20;
        try{return $this->success((new \App\Services\DeliveryHistoryService())->history((int)session('user_id'),$query));}
        catch(\OutOfBoundsException){return $this->error('Active delivery account required.',403);}
        catch(\Throwable){return $this->error('Unable to load delivery history. Please try again.',503);}
    }
    public function profile()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        if($this->request->getGet()!==[])return $this->validationError(['query'=>'Profile uses your signed-in account.']);
        try{return $this->success((new \App\Services\DeliveryHistoryService())->profile((int)session('user_id')));}
        catch(\OutOfBoundsException){return $this->error('Active delivery account required.',403);}
        catch(\Throwable){return $this->error('Unable to load your profile. Please try again.',503);}
    }
}
