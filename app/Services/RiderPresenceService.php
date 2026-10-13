<?php
namespace App\Services;
class RiderPresenceService
{
    public function record(int $riderId, array $data): void
    {
        if (array_diff(array_keys($data), ['latitude','longitude','accuracy','captured_at_ms'])) throw new \InvalidArgumentException('Only location fields are accepted.');
        $sample=$data+['order_uid'=>'presence','assignment_uid'=>'presence'];
        // Assignment presence can use approximate device GPS; delivery tracking remains stricter.
        $accuracy=$data['accuracy']??null;
        if ((!is_int($accuracy)&&!is_float($accuracy)) || !is_finite((float)$accuracy) || $accuracy<0 || $accuracy>5000) throw new \InvalidArgumentException('Use a GPS sample accurate within five kilometres.');
        $sample['accuracy']=min($accuracy,100);
        if (!DeliveryLocationService::validSample($sample)) throw new \InvalidArgumentException('Use a fresh GPS sample.');
        $db=db_connect(); $db->transBegin();
        try {
            $model=new \App\Models\DispatchModel(); $rider=$model->lockUser($riderId,true);
            if (!$rider || $rider['role']!=='delivery' || $rider['status']!=='active' || $model->availability($riderId)!=='available') throw new OrderConflictException('Only active riders set to Available can share their location.');
            $table=$db->protectIdentifiers('delivery_rider_presence',true);
            $old=$db->query("SELECT * FROM {$table} WHERE delivery_user_id=? FOR UPDATE",[$riderId])->getRowArray();
            if ($old && (int)$old['captured_at_ms'] >= $data['captured_at_ms']) throw new OrderConflictException('Use a newer GPS sample.');
            if ($old && strtotime($old['recorded_at'].' UTC') > time()-15) throw new DeliveryLocationRateLimitException();
            $row=$data+['delivery_user_id'=>$riderId,'recorded_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+300)];
            $ok=$old ? $db->table('delivery_rider_presence')->where('delivery_user_id',$riderId)->update($row) : $db->table('delivery_rider_presence')->insert($row);
            if (!$ok || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Unable to share location.');
            $db->table('delivery_rider_presence')->where('expires_at <',gmdate('Y-m-d H:i:s'))->delete();
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
    public function stop(int $riderId): void
    {
        $db=db_connect();$db->transBegin();
        try { (new \App\Models\DispatchModel())->lockUser($riderId,true);$db->table('delivery_rider_presence')->where('delivery_user_id',$riderId)->delete();$db->transCommit(); }
        catch (\Throwable $e) { $db->transRollback();throw $e; }
    }
}
