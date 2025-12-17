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
        Schema::create('system_questions', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // Catégorie de la question
            $table->text('question');
            $table->text('correct_answer'); // Réponse correcte
            $table->json('wrong_answers'); // Réponses incorrectes (tableau JSON)
            $table->string('difficulty')->default('medium'); // easy, medium, hard
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_questions');
    }
};

