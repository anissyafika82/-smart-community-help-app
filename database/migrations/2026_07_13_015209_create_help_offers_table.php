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
        Schema::create('help_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->default('person');
            $table->dateTime('available_until')->nullable();
            $table->string('image_url')->nullable();
            $table->string('location_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['available', 'claimed', 'completed', 'expired', 'cancelled'])
                ->default('available')
                ->index();
            $table->timestamps();

            $table->index(['status', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_offers');
    }
};
