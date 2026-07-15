<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'icon' => 'devices'],
            ['name' => 'Documents & Cards', 'icon' => 'badge'],
            ['name' => 'Bags & Wallets', 'icon' => 'work'],
            ['name' => 'Keys', 'icon' => 'key'],
            ['name' => 'Jewelry & Accessories', 'icon' => 'diamond'],
            ['name' => 'Clothing', 'icon' => 'checkroom'],
            ['name' => 'Pets', 'icon' => 'pets'],
            ['name' => 'Books & Stationery', 'icon' => 'menu_book'],
            ['name' => 'Other', 'icon' => 'category'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                ['name' => $category['name'], 'icon' => $category['icon']]
            );
        }
    }
}
