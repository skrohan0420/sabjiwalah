<?php

namespace App\Database\Seeds;

use App\Models\ProductModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run()
    {
        $this->seedUsers();
        $this->seedProducts();
    }

    private function seedUsers(): void
    {
        $users = new UserModel();
        $password = password_hash('Password@123', PASSWORD_DEFAULT);

        $records = [
            [
                'name'          => 'Admin User',
                'email'         => 'admin@sabjiwalah.local',
                'phone'         => '9000000001',
                'password_hash' => $password,
                'role'          => 'admin',
                'status'        => 'active',
            ],
            [
                'name'          => 'Delivery User',
                'email'         => 'delivery@sabjiwalah.local',
                'phone'         => '9000000002',
                'password_hash' => $password,
                'role'          => 'delivery',
                'status'        => 'active',
            ],
            [
                'name'          => 'Customer User',
                'email'         => 'customer@sabjiwalah.local',
                'phone'         => '9000000003',
                'password_hash' => $password,
                'role'          => 'customer',
                'status'        => 'active',
            ],
        ];

        foreach ($records as $record) {
            $existing = $users->where('email', $record['email'])->first();

            if ($existing) {
                $users->update($existing['id'], $record);
                continue;
            }

            $users->insert($record);
        }
    }

    private function seedProducts(): void
    {
        $products = new ProductModel();

        $records = [
            [
                'name'           => 'Potato',
                'slug'           => 'potato',
                'description'    => 'Fresh potatoes for everyday cooking.',
                'price'          => 30.00,
                'sale_price'     => null,
                'unit'           => 'kg',
                'stock_quantity' => 100,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Tomato',
                'slug'           => 'tomato',
                'description'    => 'Ripe tomatoes for curries and salads.',
                'price'          => 40.00,
                'sale_price'     => 35.00,
                'unit'           => 'kg',
                'stock_quantity' => 80,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Onion',
                'slug'           => 'onion',
                'description'    => 'Kitchen staple onions.',
                'price'          => 45.00,
                'sale_price'     => null,
                'unit'           => 'kg',
                'stock_quantity' => 120,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Carrot',
                'slug'           => 'carrot',
                'description'    => 'Crunchy carrots.',
                'price'          => 25.00,
                'sale_price'     => null,
                'unit'           => '500g',
                'stock_quantity' => 75,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Cabbage',
                'slug'           => 'cabbage',
                'description'    => 'Fresh cabbage.',
                'price'          => 35.00,
                'sale_price'     => null,
                'unit'           => 'piece',
                'stock_quantity' => 50,
                'is_active'      => 1,
            ],
        ];

        foreach ($records as $record) {
            $existing = $products->where('slug', $record['slug'])->first();

            if ($existing) {
                $products->update($existing['id'], $record);
                continue;
            }

            $products->insert($record);
        }
    }
}
