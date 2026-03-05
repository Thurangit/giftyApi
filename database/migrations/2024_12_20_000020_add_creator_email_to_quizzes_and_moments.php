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
        // Ajouter creator_email à la table quizzes
        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('creator_email')->nullable()->after('creator_name');
        });

        // Ajouter creator_email à la table moments
        Schema::table('moments', function (Blueprint $table) {
            $table->string('creator_email')->nullable()->after('creator_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('creator_email');
        });

        Schema::table('moments', function (Blueprint $table) {
            $table->dropColumn('creator_email');
        });
    }
};

