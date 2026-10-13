<?php
namespace App\Services;
class RiderRecommendationService
{
    public function candidates(array $filters): array
    {
        $settings=(new OperationalSettingsService())->get();
        $pickup=$settings['pickup_latitude']!==null && $settings['pickup_longitude']!==null ? ['latitude'=>(float)$settings['pickup_latitude'],'longitude'=>(float)$settings['pickup_longitude']] : null;
        $data=(new \App\Models\DispatchModel())->candidates($filters,$pickup,true);
        foreach ($data['items'] as &$rider) {
            $rider['location']=$rider['latitude']!==null ? ['latitude'=>(float)$rider['latitude'],'longitude'=>(float)$rider['longitude'],'accuracy'=>(float)$rider['accuracy'],'recorded_at'=>str_replace(' ','T',$rider['recorded_at']).'Z'] : null;
            $rider['distance_m']=isset($rider['distance_m']) ? (float)$rider['distance_m'] : null;
            unset($rider['latitude'],$rider['longitude'],$rider['accuracy'],$rider['recorded_at']);
        }
        unset($rider);
        $data['pickup']=$pickup;
        return $data;
    }
}
