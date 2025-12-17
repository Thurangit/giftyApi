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
        Schema::create('challenge_selected_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_participant_id')->constrained('challenge_participants')->onDelete('cascade');
            $table->foreignId('system_question_id')->constrained()->onDelete('cascade');
            $table->integer('question_order'); // Ordre de la question (1 à total_questions)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_selected_questions');
    }
};

