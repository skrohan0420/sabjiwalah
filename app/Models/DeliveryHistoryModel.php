<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryHistoryModel extends Model
{
    private const LATEST = 'NOT EXISTS (SELECT 1 FROM delivery_assignments n WHERE n.order_id=a.order_id AND n.id>a.id)';
    private function query(int $riderId): \CodeIgniter\Database\BaseBuilder
    {
        // One order summary per rider, using their most recent assignment. Former assignees retain history only.
        return $this->db->table('delivery_assignments a')->join('orders o','o.id=a.order_id')
            ->join('delivery_completions c','c.order_id=o.id AND c.assignment_id=a.id AND c.delivery_user_id=a.delivery_user_id','left')
            ->where('a.delivery_user_id',$riderId)->whereIn('o.order_status',['delivered','delivery_failed'])
            ->where('NOT EXISTS (SELECT 1 FROM delivery_assignments own WHERE own.order_id=a.order_id AND own.delivery_user_id=a.delivery_user_id AND own.id>a.id)',null,false);
    }
    private function credited(): string
    {
        return "(c.order_id IS NOT NULL OR a.completed_at IS NOT NULL OR (".self::LATEST." AND NOT EXISTS (SELECT 1 FROM delivery_completions proof WHERE proof.order_id=o.id)))";
    }
    private function outcomeTime(): string
    {
        return "CASE WHEN c.order_id IS NOT NULL THEN c.verified_at WHEN a.completed_at IS NOT NULL THEN a.completed_at WHEN ".self::LATEST." THEN (SELECT MAX(h.created_at) FROM order_status_history h WHERE h.order_id=o.id AND h.new_status=o.order_status AND (h.old_status IS NULL OR h.old_status<>h.new_status)) ELSE NULL END";
    }
    public function listing(int $riderId, array $filters): array
    {
        $query=$this->query($riderId);$time=$this->outcomeTime();
        if (!empty($filters['status'])) $query->where('o.order_status',$filters['status']);
        if (!empty($filters['search'])) $query->like('o.order_number',$filters['search']);
        if (!empty($filters['from_time'])) $query->where("($time) >= ".$this->db->escape($filters['from_time']),null,false);
        if (!empty($filters['to_time'])) $query->where("($time) < ".$this->db->escape($filters['to_time']),null,false);
        $total=$query->countAllResults(false);
        $items=$query->select('o.order_number, o.order_status, a.assigned_at, c.cash_amount AS amount_collected')
            ->select('('.self::LATEST.') AS is_current_assignment, ('.$this->credited().") AS credited_delivery, ($time) AS outcome_at",false)
            ->orderBy('outcome_at','DESC')->orderBy('a.id','DESC')->limit($filters['per_page'],($filters['page']-1)*$filters['per_page'])->get()->getResultArray();
        return ['items'=>$items,'pager'=>['current_page'=>$filters['page'],'page_count'=>(int)ceil($total/$filters['per_page']),'total'=>$total]];
    }
    public function completedCount(int $riderId): int
    {
        return $this->query($riderId)->where('o.order_status','delivered')->where($this->credited(),null,false)->countAllResults();
    }
}
