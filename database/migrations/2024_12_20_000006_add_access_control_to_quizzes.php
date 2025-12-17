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
            $table->enum('access_type', ['everyone', 'single', 'multiple'])->default('everyone')->after('total_amount');
            $table->string('single_participant_phone')->nullable()->after('access_type');
        });

        Schema::create('quiz_allowed_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->string('phone_number');
            $table->timestamps();

            $table->unique(['quiz_id', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_allowed_participants');

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['access_type', 'single_participant_phone']);
        });
    }
};

