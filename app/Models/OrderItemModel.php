<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class OrderItemModel extends Model
{
    use HasUid;

    protected $table = 'order_items';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'itm';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'order_id',
        'product_id',
        'product_name',
        'unit',
        'quantity',
        'unit_price',
        'total_price',
        'created_at',
    ];

    protected $validationRules = [
        'order_id'     => 'required|is_natural_no_zero',
        'product_id'   => 'permit_empty|is_natural_no_zero',
        'product_name' => 'required|max_length[150]',
        'unit'         => 'required|max_length[40]',
        'quantity'     => 'required|is_natural_no_zero',
        'unit_price'   => 'required|decimal|greater_than_equal_to[0]',
        'total_price'  => 'required|decimal|greater_than_equal_to[0]',
    ];
}
