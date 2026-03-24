<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mymind_games', function (Blueprint $table) {
            $table->id();
            $table->string('creator_name');
            $table->string('creator_email')->nullable();
            $table->string('category'); // aboutme | friends | besties | couples | coworkers
            $table->integer('questions_count');
            $table->integer('final_amount');
            $table->text('opening_message')->nullable();
            $table->string('unique_link', 64)->unique();
            $table->string('payment_phone')->nullable();
            $table->string('payment_operator')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('promo_code')->nullable();
            // JSON: [{"question_id":"am_01","answer":"Shopping compulsif 🛍️"}, ...]
            $table->json('answers');
            // Derived: ordered list of question_ids (for the partner)
            $table->json('question_ids');
            $table->string('status')->default('active'); // active | completed
            $table->string('access_code', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mymind_games');
    }
};
