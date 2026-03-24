<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mymind_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mymind_game_id')->constrained()->onDelete('cascade');
            $table->string('partner_phone')->nullable();
            $table->string('partner_operator')->nullable();
            // JSON: [{"question_id":"am_01","answer":"Shopping compulsif 🛍️"}, ...]
            $table->json('answers');
            $table->integer('score');
            $table->integer('total_questions');
            $table->boolean('won')->default(false);
            $table->string('payment_reference')->nullable();
            $table->boolean('prize_withdrawn')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mymind_attempts');
    }
};
