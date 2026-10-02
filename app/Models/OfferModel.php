<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class OfferModel extends Model
{
    use HasUid;

    protected $table = 'offers';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'off';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'name',
        'code',
        'type',
        'value',
        'minimum_order_amount',
        'maximum_discount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'is_active',
    ];

    protected $validationRules = [
        'name'                 => 'required|max_length[150]',
        'code'                 => 'permit_empty|max_length[80]|is_unique[offers.code,id,{id}]',
        'type'                 => 'required|in_list[percentage,fixed]',
        'value'                => 'required|decimal|greater_than[0]',
        'minimum_order_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'maximum_discount'     => 'permit_empty|decimal|greater_than_equal_to[0]',
        'usage_limit'          => 'permit_empty|is_natural_no_zero',
        'is_active'            => 'permit_empty|in_list[0,1]',
    ];
}
