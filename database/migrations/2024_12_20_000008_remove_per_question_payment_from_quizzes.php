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
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
        
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->enum('payment_type', ['per_question', 'final'])->default('final');
        });
        
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->integer('amount')->nullable();
        });
    }
};

