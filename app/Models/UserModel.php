<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class UserModel extends Model
{
    use HasUid;

    protected $table = 'users';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'usr';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'uid',
        'name',
        'email',
        'phone',
        'password_hash',
        'role',
        'status',
    ];

    protected $validationRules = [
        'name'          => 'required|max_length[120]',
        'email'         => 'permit_empty|valid_email|max_length[190]|is_unique[users.email,id,{id}]',
        'phone'         => 'permit_empty|max_length[30]|is_unique[users.phone,id,{id}]',
        'password_hash' => 'permit_empty|max_length[255]',
        'role'          => 'required|in_list[customer,admin,delivery]',
        'status'        => 'required|in_list[active,inactive]',
    ];
}
