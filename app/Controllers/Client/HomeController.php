<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\ProductModel;

class HomeController extends BaseController
{
    public function index(): string
    {
        $databaseStatus = [
            'connected' => false,
            'database'  => null,
            'user'      => null,
            'driver'    => null,
            'message'   => null,
        ];

        try {
            $db = db_connect();
            $row = $db->query('SELECT DATABASE() AS database_name, USER() AS database_user')->getRowArray();

            $databaseStatus = [
                'connected' => true,
                'database'  => $row['database_name'] ?? $db->database,
                'user'      => $row['database_user'] ?? $db->username,
                'driver'    => $db->DBDriver,
                'message'   => 'Connected to MySQL successfully.',
            ];
        } catch (\Throwable $exception) {
            $databaseStatus['message'] = $exception->getMessage();
        }

        $products = (new ProductModel())
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll(8);

        return view('client/home', [
            'databaseStatus' => $databaseStatus,
            'products'       => $products,
        ]);
    }
}
