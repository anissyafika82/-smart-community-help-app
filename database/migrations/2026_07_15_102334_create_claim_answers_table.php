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
        Schema::create('claim_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_question_id')->constrained()->cascadeOnDelete();
            $table->string('answer');
            $table->timestamps();

            $table->unique(['item_claim_id', 'verification_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_answers');
    }
};
