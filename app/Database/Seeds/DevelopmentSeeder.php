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
            [
                'name'           => 'Coriander Bunch',
                'slug'           => 'coriander-bunch',
                'description'    => 'Fragrant coriander leaves for chutneys, garnishing, and everyday cooking.',
                'image'          => 'https://images.unsplash.com/photo-1600326145552-327f74b9c189?auto=format&fit=crop&w=640&q=80',
                'price'          => 46.00,
                'sale_price'     => 38.00,
                'unit'           => '100 g',
                'stock_quantity' => 90,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Green Chilli',
                'slug'           => 'green-chilli',
                'description'    => 'Fresh spicy green chillies for tadka, chutneys, and pickles.',
                'image'          => 'https://images.unsplash.com/photo-1583119022894-919a68a3d0e3?auto=format&fit=crop&w=640&q=80',
                'price'          => 18.00,
                'sale_price'     => 15.00,
                'unit'           => '100 g',
                'stock_quantity' => 140,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Green Capsicum',
                'slug'           => 'green-capsicum',
                'description'    => 'Crisp green capsicum for sabzi, noodles, pizza toppings, and salads.',
                'image'          => 'https://images.unsplash.com/photo-1563565375-f3fdfdbefa83?auto=format&fit=crop&w=640&q=80',
                'price'          => 36.00,
                'sale_price'     => 32.00,
                'unit'           => '250 g',
                'stock_quantity' => 85,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Bottle Gourd',
                'slug'           => 'bottle-gourd',
                'description'    => 'Tender lauki for light curries, kofta, and healthy home meals.',
                'image'          => '/assets/images/product-placeholder.svg',
                'price'          => 44.00,
                'sale_price'     => 39.00,
                'unit'           => '1 piece',
                'stock_quantity' => 60,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Cauliflower',
                'slug'           => 'cauliflower',
                'description'    => 'Farm-fresh cauliflower for gobi sabzi, parathas, and snacks.',
                'image'          => 'https://images.unsplash.com/photo-1568584711271-6c929fb49b60?auto=format&fit=crop&w=640&q=80',
                'price'          => 55.00,
                'sale_price'     => 48.00,
                'unit'           => '1 piece',
                'stock_quantity' => 45,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Spinach Bunch',
                'slug'           => 'spinach-bunch',
                'description'    => 'Leafy spinach bunch for palak paneer, soups, and quick stir fries.',
                'image'          => 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?auto=format&fit=crop&w=640&q=80',
                'price'          => 34.00,
                'sale_price'     => 29.00,
                'unit'           => '250 g',
                'stock_quantity' => 95,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Lady Finger',
                'slug'           => 'lady-finger',
                'description'    => 'Fresh bhindi with a clean snap, perfect for dry masala sabzi.',
                'image'          => 'https://images.unsplash.com/photo-1601493700631-2b16ec4b4716?auto=format&fit=crop&w=640&q=80',
                'price'          => 42.00,
                'sale_price'     => null,
                'unit'           => '500 g',
                'stock_quantity' => 70,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Brinjal Round',
                'slug'           => 'brinjal-round',
                'description'    => 'Glossy round brinjals for bharwa baingan and everyday curry.',
                'image'          => 'https://images.unsplash.com/photo-1615484477778-ca3b77940c25?auto=format&fit=crop&w=640&q=80',
                'price'          => 38.00,
                'sale_price'     => 34.00,
                'unit'           => '500 g',
                'stock_quantity' => 65,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Cucumber',
                'slug'           => 'cucumber',
                'description'    => 'Cool cucumbers for salads, raita, sandwiches, and snacks.',
                'image'          => 'https://images.unsplash.com/photo-1449300079323-02e209d9d3a6?auto=format&fit=crop&w=640&q=80',
                'price'          => 30.00,
                'sale_price'     => 26.00,
                'unit'           => '500 g',
                'stock_quantity' => 110,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Ginger',
                'slug'           => 'ginger',
                'description'    => 'Aromatic ginger for tea, gravies, marinades, and home remedies.',
                'image'          => 'https://images.unsplash.com/photo-1615485500704-8e990f9900f7?auto=format&fit=crop&w=640&q=80',
                'price'          => 28.00,
                'sale_price'     => null,
                'unit'           => '100 g',
                'stock_quantity' => 120,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Garlic',
                'slug'           => 'garlic',
                'description'    => 'Fresh garlic bulbs for curries, tadka, pickles, and chutneys.',
                'image'          => 'https://images.unsplash.com/photo-1603186167530-6d1d77a82168?auto=format&fit=crop&w=640&q=80',
                'price'          => 32.00,
                'sale_price'     => 28.00,
                'unit'           => '100 g',
                'stock_quantity' => 100,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Apple Royal Gala',
                'slug'           => 'apple-royal-gala',
                'description'    => 'Sweet Royal Gala apples for lunchboxes, desserts, and fresh snacking.',
                'image'          => 'https://images.unsplash.com/photo-1568702846914-96b305d2aaeb?auto=format&fit=crop&w=640&q=80',
                'price'          => 145.00,
                'sale_price'     => 129.00,
                'unit'           => '500 g',
                'stock_quantity' => 55,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Banana Robusta',
                'slug'           => 'banana-robusta',
                'description'    => 'Naturally sweet bananas for breakfast, shakes, and quick snacks.',
                'image'          => 'https://images.unsplash.com/photo-1603833665858-e61d17a86224?auto=format&fit=crop&w=640&q=80',
                'price'          => 62.00,
                'sale_price'     => 54.00,
                'unit'           => '6 pcs',
                'stock_quantity' => 75,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Pomegranate',
                'slug'           => 'pomegranate',
                'description'    => 'Ruby pomegranates with juicy arils for salads, juice, and snacking.',
                'image'          => 'https://images.unsplash.com/photo-1601593768792-76d47e344a6c?auto=format&fit=crop&w=640&q=80',
                'price'          => 125.00,
                'sale_price'     => 112.00,
                'unit'           => '2 pcs',
                'stock_quantity' => 40,
                'is_active'      => 1,
            ],
            [
                'name'           => 'Sweet Corn',
                'slug'           => 'sweet-corn',
                'description'    => 'Juicy sweet corn cobs for boiling, roasting, soups, and chaat.',
                'image'          => 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?auto=format&fit=crop&w=640&q=80',
                'price'          => 50.00,
                'sale_price'     => 44.00,
                'unit'           => '2 pcs',
                'stock_quantity' => 72,
                'is_active'      => 1,
            ],
        ];

        foreach ($records as $record) {
            $existing = $products->where('slug', $record['slug'])->first();

            if ($existing) {
                $products->skipValidation(true)->update($existing['id'], $record);
                continue;
            }

            $products->skipValidation(true)->insert($record);
        }
    }
}
