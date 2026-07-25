<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ItemClaim;
use App\Models\ItemReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds. Creates two demo users and a handful of
     * sample lost/found item reports (plus one active claim) so the app
     * is usable immediately.
     */
    public function run(): void
    {
        $ahmad = User::updateOrCreate(
            ['email' => 'ahmad@findback.test'],
            [
                'name' => 'Ahmad',
                'password' => 'password',
                'role' => User::ROLE_USER,
                'phone' => '0123456789',
                'address' => 'Jalan Bukit Bintang, Kuala Lumpur',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $siti = User::updateOrCreate(
            ['email' => 'siti@findback.test'],
            [
                'name' => 'Siti',
                'password' => 'password',
                'role' => User::ROLE_USER,
                'phone' => '0198765432',
                'address' => 'Jalan Ampang, Kuala Lumpur',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $electronics = Category::where('slug', 'electronics')->first();
        $keys = Category::where('slug', 'keys')->first();
        $bags = Category::where('slug', 'bags-wallets')->first();

        if (! $electronics || ! $keys || ! $bags) {
            return;
        }

        $lostPhone = ItemReport::updateOrCreate(
            ['user_id' => $siti->id, 'item_name' => 'Black iPhone 13'],
            [
                'category_id' => $electronics->id,
                'report_type' => ItemReport::TYPE_LOST,
                'description' => 'Lost my black iPhone 13 with a clear case, somewhere around Pavilion KL.',
                'image_url' => 'https://placehold.co/800x600/1a1a2e/ffffff?text=Black+iPhone+13',
                'date_lost_or_found' => now()->subDays(2)->toDateString(),
                'location_name' => 'Pavilion Kuala Lumpur',
                'latitude' => 3.1490,
                'longitude' => 101.7133,
                'status' => ItemReport::STATUS_LOST,
                'identifying_details' => 'Lock screen wallpaper is a cat photo. Small crack on the top-right corner of the case.',
            ]
        );

        $foundPhone = ItemReport::updateOrCreate(
            ['user_id' => $ahmad->id, 'item_name' => 'Black iPhone found near Pavilion'],
            [
                'category_id' => $electronics->id,
                'report_type' => ItemReport::TYPE_FOUND,
                'description' => 'Found a black iPhone with a clear case on a bench near Pavilion KL entrance.',
                'image_url' => 'https://placehold.co/800x600/16213e/ffffff?text=Found+iPhone',
                'date_lost_or_found' => now()->subDays(1)->toDateString(),
                'location_name' => 'Pavilion Kuala Lumpur',
                'latitude' => 3.1488,
                'longitude' => 101.7130,
                'status' => ItemReport::STATUS_FOUND,
            ]
        );

        ItemReport::updateOrCreate(
            ['user_id' => $ahmad->id, 'item_name' => 'Bunch of house keys with a red keychain'],
            [
                'category_id' => $keys->id,
                'report_type' => ItemReport::TYPE_FOUND,
                'description' => 'Found a set of keys with a red rubber keychain near the LRT station entrance.',
                'image_url' => 'https://placehold.co/800x600/8b0000/ffffff?text=House+Keys',
                'date_lost_or_found' => now()->subDays(3)->toDateString(),
                'location_name' => 'Bukit Bintang LRT Station',
                'latitude' => 3.1466,
                'longitude' => 101.7108,
                'status' => ItemReport::STATUS_FOUND,
            ]
        );

        ItemReport::updateOrCreate(
            ['user_id' => $siti->id, 'item_name' => 'Brown leather wallet'],
            [
                'category_id' => $bags->id,
                'report_type' => ItemReport::TYPE_LOST,
                'description' => 'Lost a brown leather wallet with my ID and a few cards inside.',
                'image_url' => 'https://placehold.co/800x600/5c3a21/ffffff?text=Leather+Wallet',
                'date_lost_or_found' => now()->subDays(5)->toDateString(),
                'location_name' => 'Jalan Ampang, Kuala Lumpur',
                'latitude' => 3.1580,
                'longitude' => 101.7180,
                'status' => ItemReport::STATUS_LOST,
                'identifying_details' => 'Has a small tear on the coin pocket, and a passport photo of my dog tucked inside.',
            ]
        );

        // Demo an in-progress claim on the found phone, so the app has a
        // non-empty example of the claim/verification flow out of the box.
        ItemClaim::updateOrCreate(
            ['item_report_id' => $foundPhone->id, 'claimant_id' => $siti->id],
            [
                'claim_message' => 'That looks like my phone! I lost it in the same area yesterday.',
                'proof_description' => 'Lock screen wallpaper is a photo of a cat, and there is a small crack on the top-right of the case.',
                'status' => ItemClaim::STATUS_PENDING,
            ]
        );

        $lostPhone->update(['status' => ItemReport::STATUS_LOST]);
    }
}
