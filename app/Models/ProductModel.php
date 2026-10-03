<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class ProductModel extends Model
{
    use HasUid;

    protected $table = 'products';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'prd';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $beforeInsert = ['ensureUid'];
    protected $allowedFields = [
        'uid',
        'name',
        'slug',
        'description',
        'image',
        'price',
        'sale_price',
        'unit',
        'stock_quantity',
        'is_active',
    ];

    protected $validationRules = [
        'name'           => 'required|max_length[150]',
        'slug'           => 'required|max_length[190]|is_unique[products.slug,id,{id}]',
        'description'    => 'permit_empty',
        'image'          => 'permit_empty|max_length[255]',
        'price'          => 'required|decimal|greater_than_equal_to[0]',
        'sale_price'     => 'permit_empty|decimal|greater_than_equal_to[0]',
        'unit'           => 'required|max_length[40]',
        'stock_quantity' => 'required|is_natural',
        'is_active'      => 'permit_empty|in_list[0,1]',
    ];
}
