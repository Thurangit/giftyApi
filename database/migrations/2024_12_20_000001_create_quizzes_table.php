<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('creator_name');
            $table->string('unique_link')->unique();
            $table->integer('total_questions'); // 5, 10, 15, 20
            $table->integer('required_correct'); // Nombre de questions à trouver pour gagner
            $table->enum('payment_type', ['per_question', 'final']); // Montant par question ou montant final
            $table->integer('total_amount')->default(0); // Montant total (si final) ou somme des montants par question
            $table->string('status')->default('active'); // active, completed, expired
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};

