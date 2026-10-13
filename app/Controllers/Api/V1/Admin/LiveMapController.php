<?php
namespace App\Controllers\Api\V1\Admin;
class LiveMapController extends \App\Controllers\Api\V1\BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        $query=$this->request->getGet();
        $rules=['search'=>'permit_empty|max_length[120]','status'=>'permit_empty|in_list[pending,confirmed,preparing,ready_for_delivery,out_for_delivery,delivered,cancelled,delivery_failed]', 'assignment'=>'permit_empty|in_list[assigned,unassigned]', 'rider'=>'permit_empty|max_length[40]', 'availability'=>'permit_empty|in_list[available,assigned,busy,offline]', 'from'=>'permit_empty|valid_date[Y-m-d]', 'to'=>'permit_empty|valid_date[Y-m-d]'];
        foreach($rules as $key=>$rule)if(isset($query[$key])&&!is_string($query[$key]))return $this->validationError([$key=>'Must be text.']);
        if(!$this->validateData($query,$rules))return $this->validationError($this->validator->getErrors());
        if(!empty($query['from'])&&!empty($query['to'])&&$query['from']>$query['to'])return $this->validationError(['to'=>'End date must follow start date.']);
        try{return $this->success((new \App\Services\LiveMapService())->get($query));}
        catch(\Throwable $e){log_message('error','Live map request failed: {type}',['type'=>$e::class]);return $this->error('Live map is temporarily unavailable.',503);}
    }
}
