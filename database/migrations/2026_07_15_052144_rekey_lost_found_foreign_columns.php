<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-points messages/ratings/activities/reports at the new item_reports /
 * item_claims tables instead of the old help_offers / assistance_requests
 * (which are left in place, unused, as a rollback safety net rather than
 * dropped — see the FindBack migration plan).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['help_offer_id']);
        });
        DB::statement('ALTER TABLE messages CHANGE help_offer_id item_report_id BIGINT UNSIGNED NOT NULL');
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('item_report_id')->references('id')->on('item_reports')->cascadeOnDelete();
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropForeign(['assistance_request_id']);
            $table->dropUnique('uniq_rating_per_request');
        });
        DB::statement('ALTER TABLE ratings CHANGE assistance_request_id item_claim_id BIGINT UNSIGNED NOT NULL');
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreign('item_claim_id')->references('id')->on('item_claims')->cascadeOnDelete();
            $table->unique(['item_claim_id', 'rated_by_user_id'], 'uniq_rating_per_claim');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['assistance_request_id']);
        });
        DB::statement('ALTER TABLE activities CHANGE assistance_request_id item_claim_id BIGINT UNSIGNED NULL');
        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('item_claim_id')->references('id')->on('item_claims')->nullOnDelete();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['assistance_request_id']);
        });
        DB::statement('ALTER TABLE reports CHANGE assistance_request_id item_claim_id BIGINT UNSIGNED NULL');
        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('item_claim_id')->references('id')->on('item_claims')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['item_report_id']);
        });
        DB::statement('ALTER TABLE messages CHANGE item_report_id help_offer_id BIGINT UNSIGNED NOT NULL');
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('help_offer_id')->references('id')->on('help_offers')->cascadeOnDelete();
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropForeign(['item_claim_id']);
            $table->dropUnique('uniq_rating_per_claim');
        });
        DB::statement('ALTER TABLE ratings CHANGE item_claim_id assistance_request_id BIGINT UNSIGNED NOT NULL');
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreign('assistance_request_id')->references('id')->on('assistance_requests')->cascadeOnDelete();
            $table->unique(['assistance_request_id', 'rated_by_user_id'], 'uniq_rating_per_request');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['item_claim_id']);
        });
        DB::statement('ALTER TABLE activities CHANGE item_claim_id assistance_request_id BIGINT UNSIGNED NULL');
        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('assistance_request_id')->references('id')->on('assistance_requests')->nullOnDelete();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['item_claim_id']);
        });
        DB::statement('ALTER TABLE reports CHANGE item_claim_id assistance_request_id BIGINT UNSIGNED NULL');
        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('assistance_request_id')->references('id')->on('assistance_requests')->nullOnDelete();
        });
    }
};
