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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('assistance_request_id')->nullable()->constrained('assistance_requests')->cascadeOnDelete();
            $table->enum('reason', ['fake_request', 'spam', 'inappropriate_behaviour', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'dismissed', 'action_taken'])->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
