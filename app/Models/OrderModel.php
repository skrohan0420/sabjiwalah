<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'order_number',
        'user_id',
        'subtotal',
        'discount_amount',
        'delivery_charge',
        'total_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'customer_name',
        'customer_phone',
        'address_line',
        'city',
        'state',
        'postal_code',
        'notes',
    ];

    protected $validationRules = [
        'order_number'   => 'required|max_length[40]|is_unique[orders.order_number,id,{id}]',
        'user_id'        => 'required|is_natural_no_zero',
        'subtotal'       => 'required|decimal|greater_than_equal_to[0]',
        'total_amount'   => 'required|decimal|greater_than_equal_to[0]',
        'payment_method' => 'required|max_length[40]',
        'payment_status' => 'required|max_length[40]',
        'order_status'   => 'required|max_length[40]',
        'customer_name'  => 'required|max_length[120]',
        'customer_phone' => 'required|max_length[30]',
        'address_line'   => 'required|max_length[500]',
        'city'           => 'required|max_length[120]',
        'postal_code'    => 'required|max_length[20]',
    ];
}
