<?php
namespace App\Controllers\Api\V1\Admin;
use App\Controllers\Api\V1\BaseApiController;
use App\Services\OperationalSettingsService;
use App\Services\SettingsValidationException;
use App\Services\OrderConflictException;
class SettingsController extends BaseApiController
{
    public function show()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        try { $service=new OperationalSettingsService();return $this->success(['settings'=>$service->get(),'history'=>$service->history()]); }
        catch (\Throwable) { return $this->error('Settings are temporarily unavailable.',503); }
    }
    public function update()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        try { return $this->success(['settings'=>(new OperationalSettingsService())->save($this->requestData(),(int)session('user_id'))],'Settings saved.'); }
        catch(SettingsValidationException $e){return $this->validationError($e->errors);}
        catch(OrderConflictException $e){return $this->error($e->getMessage(),409);}
        catch(\OutOfBoundsException $e){return $this->error($e->getMessage(),403);}
        catch(\Throwable){return $this->error('Unable to save settings. Refresh before trying again.',503);}
    }
}
