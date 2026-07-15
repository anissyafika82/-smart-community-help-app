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
        // help_offer_id must become nullable: an SOS request is created
        // directly by a requester with no pre-existing help offer to attach
        // to (a nearby volunteer picks it up afterwards).
        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN help_offer_id BIGINT UNSIGNED NULL');

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->foreignId('helper_id')->nullable()->after('help_offer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('helper_id')->constrained('categories')->nullOnDelete();
            $table->boolean('is_sos')->default(false)->after('priority')->index();
            $table->dateTime('scheduled_at')->nullable()->after('is_sos');
            $table->decimal('latitude', 10, 7)->nullable()->after('scheduled_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('address')->nullable()->after('longitude');
            $table->decimal('helper_latitude', 10, 7)->nullable()->after('address');
            $table->decimal('helper_longitude', 10, 7)->nullable()->after('helper_latitude');
            $table->dateTime('helper_location_updated_at')->nullable()->after('helper_longitude');
            $table->dateTime('confirmed_at')->nullable()->after('resolved_at');
        });

        // Backfill helper_id for existing rows from their help offer, so
        // stats/badges that key off assistance_requests.helper_id are
        // correct for data created before this migration.
        DB::statement(
            'UPDATE assistance_requests ar '.
            'JOIN help_offers ho ON ho.id = ar.help_offer_id '.
            'SET ar.helper_id = ho.helper_id, ar.category_id = ho.category_id '.
            'WHERE ar.help_offer_id IS NOT NULL'
        );

        DB::statement("ALTER TABLE assistance_requests MODIFY COLUMN status ENUM('pending', 'approved', 'on_the_way', 'pending_confirmation', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE assistance_requests MODIFY COLUMN priority ENUM('low', 'medium', 'high', 'emergency') NOT NULL DEFAULT 'medium'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE assistance_requests SET status = 'completed' WHERE status = 'pending_confirmation'");
        DB::statement("UPDATE assistance_requests SET priority = 'high' WHERE priority = 'emergency'");
        DB::statement("ALTER TABLE assistance_requests MODIFY COLUMN status ENUM('pending', 'approved', 'on_the_way', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE assistance_requests MODIFY COLUMN priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium'");

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropForeign(['helper_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'helper_id', 'category_id', 'is_sos', 'scheduled_at',
                'latitude', 'longitude', 'address',
                'helper_latitude', 'helper_longitude', 'helper_location_updated_at',
                'confirmed_at',
            ]);
        });

        DB::statement('ALTER TABLE assistance_requests MODIFY COLUMN help_offer_id BIGINT UNSIGNED NOT NULL');
    }
};
