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
        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_offer_id')->constrained('help_offers')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])
                ->default('pending')
                ->index();
            $table->text('notes')->nullable();
            $table->dateTime('requested_at')->useCurrent();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['help_offer_id', 'requester_id', 'status'], 'uniq_active_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistance_requests');
    }
};
