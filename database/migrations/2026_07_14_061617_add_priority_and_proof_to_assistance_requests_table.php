<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('quantity');
            $table->string('proof_image_url')->nullable()->after('notes');
        });

        // Raw SQL to add 'on_the_way' to the status enum — Laravel's
        // Blueprint::enum()->change() requires doctrine/dbal, which this
        // project doesn't otherwise need.
        DB::statement("ALTER TABLE assistance_requests MODIFY COLUMN status ENUM('pending', 'approved', 'on_the_way', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE assistance_requests SET status = 'approved' WHERE status = 'on_the_way'");
        DB::statement("ALTER TABLE assistance_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropColumn(['priority', 'proof_image_url']);
        });
    }
};
