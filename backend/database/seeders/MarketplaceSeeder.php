<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        $admin = User::updateOrCreate(
            ['email' => 'admin@marketplace.test'],
            [
                'name' => 'Marketplace Admin',
                'phone' => '03000000001',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'is_active' => true,
                'house_number' => null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Sellers
        |--------------------------------------------------------------------------
        */

        $seller1 = User::updateOrCreate(
            ['email' => 'seller1@marketplace.test'],
            [
                'name' => 'Ahmed Khan',
                'phone' => '03000000002',
                'password' => Hash::make('password'),
                'role' => UserRole::SELLER,
                'is_active' => true,
                'house_number' => 'House 302',
            ]
        );

        $seller2 = User::updateOrCreate(
            ['email' => 'seller2@marketplace.test'],
            [
                'name' => 'Ali Raza',
                'phone' => '03000000003',
                'password' => Hash::make('password'),
                'role' => UserRole::SELLER,
                'is_active' => true,
                'house_number' => 'House 410',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Buyer
        |--------------------------------------------------------------------------
        */

        $buyer = User::updateOrCreate(
            ['email' => 'buyer@marketplace.test'],
            [
                'name' => 'Muhammad Buyer',
                'phone' => '03000000004',
                'password' => Hash::make('password'),
                'role' => UserRole::BUYER,
                'is_active' => true,
                'house_number' => 'House 205',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        $food = Category::updateOrCreate(
            ['slug' => 'food'],
            [
                'name' => 'Food',
                'icon' => '🍔',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $fashion = Category::updateOrCreate(
            ['slug' => 'fashion'],
            [
                'name' => 'Fashion',
                'icon' => '👕',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $grocery = Category::updateOrCreate(
            ['slug' => 'grocery'],
            [
                'name' => 'Grocery',
                'icon' => '🛒',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Stores
        |--------------------------------------------------------------------------
        */

        $store1 = Store::updateOrCreate(
            ['slug' => 'house-302-foods'],
            [
                'user_id' => $seller1->id,
                'store_name' => 'House 302 Foods',
                'house_number' => 'House 302',
                'is_open' => true,
                'is_active' => true,
                'bonus_text' => 'Fresh homemade food',
                'description' => 'Homemade food prepared fresh for the neighborhood.',
            ]
        );

        $store2 = Store::updateOrCreate(
            ['slug' => 'house-410-fashion'],
            [
                'user_id' => $seller2->id,
                'store_name' => 'House 410 Fashion',
                'house_number' => 'House 410',
                'is_open' => true,
                'is_active' => true,
                'bonus_text' => 'Local fashion deals',
                'description' => 'Affordable fashion products from your neighborhood.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Store 1 Products
        |--------------------------------------------------------------------------
        */

        $products1 = [
            [
                'title' => 'Chicken Karahi',
                'description' => 'Fresh homemade chicken karahi.',
                'price' => 850.00,
                'badge' => 'Popular',
                'category_id' => $food->id,
                'sales_count' => 25,
                'sort_order' => 1,
            ],
            [
                'title' => 'Seekh Kabab',
                'description' => 'Homemade juicy seekh kababs.',
                'price' => 450.00,
                'badge' => 'Best Seller',
                'category_id' => $food->id,
                'sales_count' => 40,
                'sort_order' => 2,
            ],
            [
                'title' => 'Fresh Roti',
                'description' => 'Fresh homemade rotis.',
                'price' => 30.00,
                'badge' => null,
                'category_id' => $food->id,
                'sales_count' => 60,
                'sort_order' => 3,
            ],
            [
                'title' => 'Cooking Oil 1L',
                'description' => 'Cooking oil for everyday use.',
                'price' => 650.00,
                'badge' => null,
                'category_id' => $grocery->id,
                'sales_count' => 12,
                'sort_order' => 4,
            ],
        ];

        foreach ($products1 as $product) {
            Product::updateOrCreate(
                [
                    'store_id' => $store1->id,
                    'title' => $product['title'],
                ],
                [
                    ...$product,
                    'store_id' => $store1->id,
                    'is_available' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Store 2 Products
        |--------------------------------------------------------------------------
        */

        $products2 = [
            [
                'title' => 'Hair Clips',
                'description' => 'Stylish everyday hair clips.',
                'price' => 150.00,
                'badge' => 'New',
                'category_id' => $fashion->id,
                'sales_count' => 18,
                'sort_order' => 1,
            ],
            [
                'title' => 'Ladies Scarf',
                'description' => 'Comfortable everyday scarf.',
                'price' => 750.00,
                'badge' => null,
                'category_id' => $fashion->id,
                'sales_count' => 9,
                'sort_order' => 2,
            ],
            [
                'title' => 'Kids Cap',
                'description' => 'Comfortable casual kids cap.',
                'price' => 350.00,
                'badge' => 'Popular',
                'category_id' => $fashion->id,
                'sales_count' => 14,
                'sort_order' => 3,
            ],
        ];

        foreach ($products2 as $product) {
            Product::updateOrCreate(
                [
                    'store_id' => $store2->id,
                    'title' => $product['title'],
                ],
                [
                    ...$product,
                    'store_id' => $store2->id,
                    'is_available' => true,
                ]
            );
        }

        $this->command->info('Marketplace test data seeded successfully.');
        $this->command->info('Admin: admin@marketplace.test / password');
        $this->command->info('Seller 1: seller1@marketplace.test / password');
        $this->command->info('Seller 2: seller2@marketplace.test / password');
        $this->command->info('Buyer: buyer@marketplace.test / password');
    }
}