<?php
namespace App\Services;
use App\Models\DispatchModel;
use App\Models\OrderDeliveryPinModel;
use App\Models\DeliveryCompletionModel;
class DeliveryCompletionService
{
    public function complete(string $uid, int $riderId, string $pin, bool $cashReceived): void
    {
        $db=db_connect(); $dispatch=new DispatchModel(); $pins=new OrderDeliveryPinModel(); $records=new DeliveryCompletionModel(); $committed=false;
        if (!$db->transBegin()) throw new \RuntimeException('Unable to begin delivery completion.');
        try {
            $order=$dispatch->lockOrder($uid); $actor=$dispatch->lockUser($riderId,true);
            $assignment=$order?$dispatch->latest((int)$order['id']):null;
            if (!$order || !$actor || $actor['role']!=='delivery' || $actor['status']!=='active' || !$assignment || (int)$assignment['delivery_user_id']!==$riderId) throw new \OutOfBoundsException('Order not found.');
            $id=(int)$order['id']; $proof=$records->locked($id);
            if ($order['order_status']==='delivered' && $proof && (int)$proof['delivery_user_id']===$riderId) {
                if (!$db->transCommit()) throw new \RuntimeException('Unable to confirm completion.');
                return;
            }
            if ($order['order_status']!=='out_for_delivery' || $proof) throw new OrderConflictException('This order cannot be completed. Refresh its details.');
            $cod=$order['payment_method']==='cod';
            if (($cod && ($order['payment_status']!=='pending' || !$cashReceived)) || (!$cod && $order['payment_status']!=='paid')) throw new DeliveryVerificationException($cod?'Confirm receipt of the full COD amount before completing delivery.':'This order has not been paid.');
            $record=$pins->locked($id); $zone=new \DateTimeZone(config('App')->appTimezone); $now=time();
            if (!$record || (new \DateTimeImmutable($record['expires_at'],$zone))->getTimestamp()<=$now) throw new DeliveryVerificationException('PIN missing or expired. Ask the customer to generate a new PIN.',409);
            $window=!empty($record['attempt_window_ends_at'])?(new \DateTimeImmutable($record['attempt_window_ends_at'],$zone))->getTimestamp():0;
            $attempts=$window>$now?(int)$record['failed_attempts']:0;
            if ($attempts>=5) throw new DeliveryVerificationException('Too many incorrect PINs. Wait for the 30-minute attempt window to end.',429);
            if (!password_verify($pin,$record['pin_hash'])) {
                if (!$pins->update($id,['failed_attempts'=>$attempts+1,'attempt_window_ends_at'=>date('Y-m-d H:i:s',$window>$now?$window:$now+1800)]) || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Unable to record verification attempt.');
                $committed=true;
                throw new DeliveryVerificationException($attempts+1>=5?'Too many incorrect PINs. Wait 30 minutes before trying again.':'Incorrect delivery PIN.', $attempts+1>=5?429:422);
            }
            $stamp=date('Y-m-d H:i:s');
            if ($records->insert(['order_id'=>$id,'assignment_id'=>(int)$assignment['id'],'delivery_user_id'=>$riderId,'verified_at'=>$stamp,'cash_amount'=>$cod?$order['total_amount']:null,'collected_at'=>$cod?$stamp:null])===false) throw new \RuntimeException('Unable to save delivery record.');
            (new OrderService())->changeStatus($id,'delivered',$riderId,'Customer delivery PIN verified'.($cod?'; full COD cash collected.':'.'),'out_for_delivery');
            if ($cod && !$db->table('orders')->where('id',$id)->update(['payment_status'=>'paid'])) throw new \RuntimeException('Unable to save payment.');
            if (!$db->table('delivery_assignments')->where('id',$assignment['id'])->update(['completed_at'=>$stamp])) throw new \RuntimeException('Unable to complete assignment.');
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Unable to commit completion.');
        } catch (\Throwable $exception) { if (!$committed) $db->transRollback(); throw $exception; }
    }
    public function reconcile(string $uid, int $adminId): void
    {
        $db=db_connect(); $dispatch=new DispatchModel(); $records=new DeliveryCompletionModel();
        if (!$db->transBegin()) throw new \RuntimeException('Unable to begin reconciliation.');
        try {
            $order=$dispatch->lockOrder($uid); $actor=$dispatch->lockUser($adminId,true);
            if (!$actor || $actor['role']!=='admin' || $actor['status']!=='active') throw new \OutOfBoundsException('Record not found.');
            $record=$order?$records->locked((int)$order['id']):null;
            if (!$record || $record['cash_amount']===null) throw new \OutOfBoundsException('Cash record not found.');
            if (!$record['reconciled_at']) {
                $stamp=date('Y-m-d H:i:s');
                if (!$records->update((int)$order['id'],['reconciled_by'=>$adminId,'reconciled_at'=>$stamp])
                    || !(new \App\Models\OrderStatusHistoryModel())->insert(['order_id'=>(int)$order['id'],'old_status'=>$order['order_status'],'new_status'=>$order['order_status'],'changed_by'=>$adminId,'notes'=>'COD cash handover reconciled: Rs '.$record['cash_amount'].'.','created_at'=>$stamp])) throw new \RuntimeException('Unable to reconcile cash.');
            }
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Unable to commit reconciliation.');
        } catch (\Throwable $exception) { $db->transRollback(); throw $exception; }
    }
}
