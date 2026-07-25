<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The categories table still held rows from the pre-migration community-help
 * app (Elderly Support, Medical Assistance, etc.) — nonsensical for a lost
 * & found item report. No item report referenced them yet, so a plain
 * delete is safe (category_id cascade-deletes on item_reports).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->whereIn('slug', [
            'elderly-support',
            'emergency-help',
            'food-assistance',
            'grocery-pickup',
            'medical-assistance',
            'transportation',
            'volunteer-service',
            'wheelchair-borrowing',
        ])->delete();
    }

    public function down(): void
    {
        // Stale data intentionally not restored.
    }
};
