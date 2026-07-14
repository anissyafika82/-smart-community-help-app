<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HelpOffer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds. Creates a demo helper, requester, and
     * a handful of sample help offers so the app is usable immediately.
     */
    public function run(): void
    {
        $helper = User::updateOrCreate(
            ['email' => 'helper@communityhelp.test'],
            [
                'name' => 'Ahmad Volunteer',
                'password' => 'password',
                'role' => User::ROLE_HELPER,
                'phone' => '0123456789',
                'address' => 'Jalan Bukit Bintang, Kuala Lumpur',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'requester@communityhelp.test'],
            [
                'name' => 'Komuniti Kasih',
                'password' => 'password',
                'role' => User::ROLE_REQUESTER,
                'phone' => '0198765432',
                'address' => 'Jalan Ampang, Kuala Lumpur',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $category = Category::first();

        if (! $category) {
            return;
        }

        $samples = [
            [
                'title' => 'Free ride to hospital appointments',
                'description' => 'I have a car and free time on weekday mornings — can drive elderly or disabled folks to nearby hospitals/clinics.',
                'quantity' => 3,
                'unit' => 'trips',
                'location_address' => 'Jalan Bukit Bintang, Kuala Lumpur',
                'latitude' => 3.1466,
                'longitude' => 101.7108,
            ],
            [
                'title' => 'Spare wheelchair available to borrow',
                'description' => 'Lightweight foldable wheelchair, barely used, available for short-term loan to anyone who needs it.',
                'quantity' => 1,
                'unit' => 'unit',
                'location_address' => 'Jalan Bukit Bintang, Kuala Lumpur',
                'latitude' => 3.1478,
                'longitude' => 101.7113,
            ],
            [
                'title' => 'Volunteers needed for food bank sorting',
                'description' => 'Looking for volunteers to help sort and pack groceries at the community food bank this Saturday.',
                'quantity' => 5,
                'unit' => 'volunteers',
                'location_address' => 'Jalan Bukit Bintang, Kuala Lumpur',
                'latitude' => 3.1450,
                'longitude' => 101.7090,
            ],
        ];

        foreach ($samples as $sample) {
            HelpOffer::updateOrCreate(
                ['helper_id' => $helper->id, 'title' => $sample['title']],
                array_merge($sample, [
                    'category_id' => $category->id,
                    'available_until' => now()->addDays(3),
                    'status' => HelpOffer::STATUS_AVAILABLE,
                ])
            );
        }
    }
}
