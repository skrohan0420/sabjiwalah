<?php
namespace App\Services;

class LiveMapService
{
    public function get(array $filters): array
    {
        $db=db_connect();
        $query=$db->table('orders o')->join('delivery_assignments a','a.order_id=o.id AND NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id=a.order_id AND n.id>a.id)','left',false)->join('users u','u.id=a.delivery_user_id','left');
        $query->whereIn('o.order_status',empty($filters['status'])?['pending','confirmed','preparing','ready_for_delivery','out_for_delivery']:[$filters['status']]);
        if(!empty($filters['search']))$query->like('o.order_number',$filters['search']);
        if(!empty($filters['rider']))$query->where('u.uid',$filters['rider']);
        if(($filters['assignment']??'')==='assigned')$query->where('a.id IS NOT NULL',null,false);
        if(($filters['assignment']??'')==='unassigned')$query->where('a.id',null);
        if(!empty($filters['from']))$query->where('o.created_at >=',$filters['from'].' 00:00:00');
        if(!empty($filters['to']))$query->where('o.created_at <',(new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00');
        $orders=$query->select('o.uid,o.order_number,o.order_status,o.delivery_latitude,o.delivery_longitude,u.uid AS rider_uid,u.name AS rider_name')->orderBy('o.id','DESC')->get()->getResultArray();
        foreach($orders as &$order){$order['destination']=$this->point($order['delivery_latitude'],$order['delivery_longitude']);unset($order['delivery_latitude'],$order['delivery_longitude']);}unset($order);
        $riders=$db->table('users u')->join('delivery_personnel_profiles p','p.user_id=u.id')->where('u.role','delivery')->where('u.status','active')->select('u.id,u.uid,u.name,p.availability')->get()->getResultArray();
        $counts=(new \App\Models\AdminDeliveryPersonnelModel())->counts(array_column($riders,'id'));
        $presence=[];
        if($db->tableExists('delivery_rider_presence'))foreach($db->table('delivery_rider_presence')->where('expires_at >',gmdate('Y-m-d H:i:s'))->get()->getResultArray() as $row)$presence[$row['delivery_user_id']]=$row;
        if($db->tableExists('delivery_locations')){
            $tracking=$db->table('delivery_locations l')->join('delivery_assignments a','a.id=l.assignment_id AND a.order_id=l.order_id AND a.delivery_user_id=l.delivery_user_id')->join('orders o','o.id=l.order_id')->where('o.order_status','out_for_delivery')->where('NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id=a.order_id AND n.id>a.id)',null,false)->where('l.recorded_at >=',gmdate('Y-m-d H:i:s',time()-300))->where('l.expires_at >',gmdate('Y-m-d H:i:s'))->select('l.*')->get()->getResultArray();
            foreach($tracking as $row)if(!isset($presence[$row['delivery_user_id']])||$row['recorded_at']>$presence[$row['delivery_user_id']]['recorded_at'])$presence[$row['delivery_user_id']]=$row;
        }
        $allRiders=[];
        foreach($riders as $rider){
            $work=$counts[$rider['id']]??[];
            $state=($work['out_for_delivery']??0)>0?'busy':(($work['workload']??0)>0?'assigned':($rider['availability']==='available'?'available':'offline'));
            $row=$presence[$rider['id']]??null;$position=$row?$this->point($row['latitude'],$row['longitude']):null;
            $accuracy=$row?(float)$row['accuracy']:null;
            $location=$position&&$accuracy!==null&&$accuracy>=0&&$accuracy<=5000?$position+['accuracy'=>$accuracy,'recorded_at'=>str_replace(' ','T',$row['recorded_at']).'Z']:null;
            $allRiders[]=['uid'=>$rider['uid'],'name'=>$rider['name'],'availability'=>$state,'location'=>$location];
        }
        $shownRiders=array_values(array_filter($allRiders,fn($r)=> (empty($filters['rider'])||$r['uid']===$filters['rider'])&&(empty($filters['availability'])||$r['availability']===$filters['availability'])));
        if(!empty($filters['availability'])){$uids=array_column($shownRiders,'uid');$orders=array_values(array_filter($orders,fn($o)=>in_array($o['rider_uid'],$uids,true)));}
        $settings=(new OperationalSettingsService())->get();
        return ['orders'=>$orders,'riders'=>$shownRiders,'rider_options'=>array_map(fn($r)=>['uid'=>$r['uid'],'name'=>$r['name']],$allRiders),'pickup'=>$this->point($settings['pickup_latitude'],$settings['pickup_longitude']),'generated_at'=>gmdate('Y-m-d\TH:i:s\Z')];
    }
    private function point($latitude,$longitude):?array
    {
        return is_numeric($latitude)&&is_numeric($longitude)&&is_finite((float)$latitude)&&is_finite((float)$longitude)&&abs((float)$latitude)<=90&&abs((float)$longitude)<=180?['latitude'=>(float)$latitude,'longitude'=>(float)$longitude]:null;
    }
}
