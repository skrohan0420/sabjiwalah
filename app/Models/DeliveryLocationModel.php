<?php
namespace App\Models;

class DeliveryLocationModel extends \CodeIgniter\Model
{
    protected $table = 'delivery_locations';
    protected $primaryKey = 'delivery_user_id';
    protected $allowedFields = [];

    public function clearOrder(int $orderId): void
    {
        // Existing installations continue status operations until the new migration is deployed.
        if ($this->db->tableExists($this->table) && !$this->db->table($this->table)->where('order_id', $orderId)->update(['latitude' => null, 'longitude' => null, 'accuracy' => null])) throw new \RuntimeException('Unable to clear delivery location.');
    }
}
