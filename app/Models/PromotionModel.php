<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class PromotionModel extends Model
{
    use HasUid;

    protected $table = 'promotions';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'pro';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'title',
        'image',
        'link',
        'position',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $validationRules = [
        'title'     => 'required|max_length[150]',
        'image'     => 'permit_empty|max_length[255]',
        'link'      => 'permit_empty|max_length[255]',
        'position'  => 'permit_empty|max_length[80]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];
}
