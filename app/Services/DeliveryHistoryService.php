<?php

namespace App\Services;

class DeliveryHistoryService
{
    private function rider(int $id): array
    {
        $rider=(new \App\Models\UserModel())->select('id,uid,name,phone,role,status')->find($id);
        if (!$rider || $rider['role']!=='delivery' || $rider['status']!=='active') throw new \OutOfBoundsException('Active delivery account required.');
        return $rider;
    }
    public function history(int $id, array $filters): array
    {
        $this->rider($id);$zone=new \DateTimeZone(config('App')->appTimezone);$india=new \DateTimeZone('Asia/Kolkata');
        if (!empty($filters['from'])) $filters['from_time']=(new \DateTimeImmutable($filters['from'],$india))->setTimezone($zone)->format('Y-m-d H:i:s');
        if (!empty($filters['to'])) $filters['to_time']=(new \DateTimeImmutable($filters['to'],$india))->modify('+1 day')->setTimezone($zone)->format('Y-m-d H:i:s');
        $data=(new \App\Models\DeliveryHistoryModel())->listing($id,$filters);
        foreach($data['items'] as &$row){
            foreach(['assigned_at','outcome_at'] as $key) $row[$key]=$row[$key]?(new \DateTimeImmutable($row[$key],$zone))->format(DATE_ATOM):null;
            $row['is_current_assignment']=(bool)$row['is_current_assignment'];
            $row['credited_delivery']=$row['order_status']==='delivered'&&(bool)$row['credited_delivery'];
            $row['amount_collected']=$row['amount_collected']===null?null:number_format((float)$row['amount_collected'],2,'.','');
        }unset($row);
        return $data+['timezone'=>'Asia/Kolkata'];
    }
    public function profile(int $id): array
    {
        $rider=$this->rider($id);$dashboard=(new DeliveryDashboardService())->snapshot($id);
        return ['name'=>$rider['name'],'phone'=>$rider['phone'],'account_status'=>$rider['status'],'availability'=>$dashboard['availability'],
            'completed_total'=>(new \App\Models\DeliveryHistoryModel())->completedCount($id),'completed_today'=>$dashboard['completed_today'],
            'cod_collected_today'=>$dashboard['cod_collected_today'],'cod'=>(new \App\Models\DeliveryCompletionModel())->riderCashTotals($id),
            'day'=>$dashboard['day'],'timezone'=>$dashboard['timezone'],'generated_at'=>$dashboard['generated_at']];
    }
}
