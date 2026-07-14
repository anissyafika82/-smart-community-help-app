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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistance_request_id')->constrained('assistance_requests')->cascadeOnDelete();
            $table->foreignId('rated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();

            // One rating per person, per completed request — either party
            // (helper or requester) can rate the other once.
            $table->unique(['assistance_request_id', 'rated_by_user_id'], 'uniq_rating_per_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
