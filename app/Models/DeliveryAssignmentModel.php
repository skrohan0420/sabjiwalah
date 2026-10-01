<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryAssignmentModel extends Model
{
    protected $table = 'delivery_assignments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'order_id',
        'delivery_user_id',
        'assigned_by',
        'assigned_at',
        'completed_at',
    ];

    protected $validationRules = [
        'order_id'         => 'required|is_natural_no_zero',
        'delivery_user_id' => 'required|is_natural_no_zero',
        'assigned_by'      => 'required|is_natural_no_zero',
        'assigned_at'      => 'required|valid_date',
        'completed_at'     => 'permit_empty|valid_date',
    ];
}
