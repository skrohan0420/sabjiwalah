<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class DeliveryAssignmentModel extends Model
{
    use HasUid;

    protected $table = 'delivery_assignments';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'das';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'uid',
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
