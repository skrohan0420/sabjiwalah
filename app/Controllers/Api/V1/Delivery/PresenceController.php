<?php
namespace App\Controllers\Api\V1\Delivery;
class PresenceController extends \App\Controllers\Api\V1\BaseApiController
{
    public function create()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        try { (new \App\Services\RiderPresenceService())->record((int)\App\Services\AuthService::id('delivery'),$this->requestData()); return $this->success(['expires_in'=>300],'Location shared for five minutes.'); }
        catch (\App\Services\DeliveryLocationRateLimitException) { $this->response->setHeader('Retry-After','15');return $this->error('Wait 15 seconds before refreshing your location.',429); }
        catch (\App\Services\OrderConflictException $e) {return $this->error($e->getMessage(),409);}
        catch (\InvalidArgumentException $e) {return $this->error($e->getMessage(),422);}
        catch (\Throwable) {return $this->error('Location sharing is unavailable.',503);}
    }
    public function stop()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        if ($this->requestData()!==[]) return $this->error('No fields are accepted.',422);
        try {(new \App\Services\RiderPresenceService())->stop((int)\App\Services\AuthService::id('delivery'));return $this->success([],'Shared location cleared.');}
        catch (\Throwable) {return $this->error('Unable to clear location. It expires within five minutes.',503);}
    }
}
