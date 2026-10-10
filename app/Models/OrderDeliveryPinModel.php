<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderDeliveryPinModel extends Model
{
    protected $table = 'order_delivery_pins';
    protected $primaryKey = 'order_id';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['order_id', 'pin_hash', 'issued_at', 'expires_at', 'failed_attempts', 'attempt_window_ends_at'];

    public function locked(int $orderId): ?array
    {
        $sql = $this->db->table($this->table)->where('order_id', $orderId)->getCompiledSelect();
        return $this->db->query($sql . ' FOR UPDATE')->getRowArray();
    }
}
