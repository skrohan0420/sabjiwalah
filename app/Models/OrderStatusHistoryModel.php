<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderStatusHistoryModel extends Model
{
    protected $table = 'order_status_history';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
    protected $allowedFields = [
        'order_id',
        'old_status',
        'new_status',
        'changed_by',
        'notes',
        'created_at',
    ];

    protected $validationRules = [
        'order_id'   => 'required|is_natural_no_zero',
        'old_status' => 'permit_empty|max_length[40]',
        'new_status' => 'required|max_length[40]',
        'changed_by' => 'permit_empty|is_natural_no_zero',
    ];
}
