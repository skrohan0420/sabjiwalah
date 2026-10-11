<?php
namespace App\Models;
use CodeIgniter\Model;
class DeliveryCompletionModel extends Model
{
    protected $table = 'delivery_completions';
    protected $primaryKey = 'order_id';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['order_id','assignment_id','delivery_user_id','verified_at','cash_amount','collected_at','reconciled_by','reconciled_at'];
    public function locked(int $orderId): ?array
    {
        return $this->db->query($this->db->table($this->table)->where('order_id',$orderId)->getCompiledSelect().' FOR UPDATE')->getRowArray();
    }
    public function riderToday(int $riderId, string $start, string $end): array
    {
        $completed = $this->db->table($this->table)->where('delivery_user_id', $riderId)
            ->where('verified_at >=', $start)->where('verified_at <', $end)->countAllResults();
        // Legacy deliveries without a verification record need a recorded completion time.
        $legacy = $this->db->table('delivery_assignments a')->join('orders o', 'o.id = a.order_id')
            ->join('delivery_completions c', 'c.order_id = o.id', 'left')
            ->join('order_status_history h', "h.order_id = o.id AND h.new_status = 'delivered' AND h.changed_by = a.delivery_user_id", 'left')
            ->where('a.delivery_user_id', $riderId)->where('o.order_status', 'delivered')->where('c.order_id', null)
            ->where('NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id = a.order_id AND n.id > a.id)', null, false)
            ->groupStart()->groupStart()->where('a.completed_at >=', $start)->where('a.completed_at <', $end)->groupEnd()
            ->orGroupStart()->where('a.completed_at', null)->where('h.created_at >=', $start)->where('h.created_at <', $end)->groupEnd()->groupEnd()
            ->select('COUNT(DISTINCT o.id) AS total', false)->get()->getRowArray();
        $cash = $this->db->table($this->table)->where('delivery_user_id', $riderId)
            ->where('collected_at >=', $start)->where('collected_at <', $end)
            ->select('COALESCE(SUM(cash_amount),0) AS total', false)->get()->getRowArray();
        return ['completed_today' => $completed + (int) $legacy['total'], 'cod_collected_today' => number_format((float) $cash['total'], 2, '.', '')];
    }
    public function cashListing(array $filters): array
    {
        $query=$this->db->table('delivery_completions c')->join('orders o','o.id=c.order_id')->join('users u','u.id=c.delivery_user_id')->join('users manager','manager.id=c.reconciled_by','left')->where('c.cash_amount IS NOT NULL',null,false);
        if (($filters['status']??'')==='pending') $query->where('c.reconciled_at',null);
        if (($filters['status']??'')==='reconciled') $query->where('c.reconciled_at IS NOT NULL',null,false);
        if (($filters['search']??'')!=='') $query->groupStart()->like('o.order_number',$filters['search'])->orLike('u.name',$filters['search'])->groupEnd();
        $total=$query->countAllResults(false);
        $items=$query->select('o.uid, o.order_number, u.name AS rider_name, c.cash_amount, c.collected_at, c.verified_at, c.reconciled_at, manager.name AS reconciled_by_name')->orderBy('c.collected_at','DESC')->orderBy('c.order_id','DESC')->limit($filters['per_page'],($filters['page']-1)*$filters['per_page'])->get()->getResultArray();
        $totals=$this->db->table($this->table)->select("COALESCE(SUM(cash_amount),0) AS collected, COALESCE(SUM(CASE WHEN reconciled_at IS NULL THEN cash_amount ELSE 0 END),0) AS pending, COALESCE(SUM(CASE WHEN reconciled_at IS NOT NULL THEN cash_amount ELSE 0 END),0) AS reconciled",false)->get()->getRowArray();
        return ['items'=>$items,'totals'=>$totals,'pager'=>['current_page'=>$filters['page'],'page_count'=>(int)ceil($total/$filters['per_page']),'total'=>$total]];
    }
}
