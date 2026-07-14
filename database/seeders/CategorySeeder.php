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
            ['name' => 'Food Assistance', 'icon' => 'restaurant'],
            ['name' => 'Medical Assistance', 'icon' => 'local_hospital'],
            ['name' => 'Transportation', 'icon' => 'directions_car'],
            ['name' => 'Elderly Support', 'icon' => 'elderly'],
            ['name' => 'Grocery Pickup', 'icon' => 'shopping_cart'],
            ['name' => 'Wheelchair Borrowing', 'icon' => 'wheelchair_pickup'],
            ['name' => 'Volunteer Service', 'icon' => 'volunteer_activism'],
            ['name' => 'Emergency Help', 'icon' => 'emergency'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                ['name' => $category['name'], 'icon' => $category['icon']]
            );
        }
    }
}
