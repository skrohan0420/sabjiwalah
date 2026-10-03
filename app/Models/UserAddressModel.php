<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class UserAddressModel extends Model
{
    use HasUid;

    protected $table = 'user_addresses';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'adr';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'uid',
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'is_default',
    ];

    protected $validationRules = [
        'user_id'        => 'required|is_natural_no_zero',
        'label'          => 'required|max_length[60]',
        'recipient_name' => 'required|max_length[120]',
        'phone'          => 'required|max_length[30]',
        'address_line_1' => 'required|max_length[255]',
        'address_line_2' => 'permit_empty|max_length[255]',
        'city'           => 'required|max_length[120]',
        'state'          => 'permit_empty|max_length[120]',
        'postal_code'    => 'required|max_length[20]',
        'is_default'     => 'permit_empty|in_list[0,1]',
    ];
}
