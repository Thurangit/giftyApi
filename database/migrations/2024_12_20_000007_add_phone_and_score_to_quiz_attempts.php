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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->string('participant_phone')->nullable()->after('participant_name');
            $table->decimal('score', 5, 2)->default(0)->after('correct_answers'); // Score en pourcentage
        });

        // Ajouter un index unique pour empêcher qu'une personne réponde deux fois
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unique(['quiz_id', 'participant_phone'], 'unique_quiz_participant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropUnique('unique_quiz_participant');
            $table->dropColumn(['participant_phone', 'score']);
        });
    }
};

