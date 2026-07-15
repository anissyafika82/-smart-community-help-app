<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_report_id')->constrained('item_reports')->cascadeOnDelete();
            $table->foreignId('claimant_id')->constrained('users')->cascadeOnDelete();
            $table->text('claim_message');
            $table->text('proof_description')->nullable();
            $table->string('proof_image_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->timestamps();

            $table->unique(['item_report_id', 'claimant_id', 'status'], 'uniq_active_claim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_claims');
    }
};
