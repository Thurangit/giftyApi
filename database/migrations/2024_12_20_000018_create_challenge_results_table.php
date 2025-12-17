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
        Schema::create('challenge_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->foreignId('winner_id')->nullable()->constrained('challenge_participants')->onDelete('set null');
            $table->integer('creator_score'); // Score du créateur
            $table->integer('participant_score'); // Score du participant
            $table->integer('total_amount'); // Somme des deux montants
            $table->integer('won_amount'); // Montant gagné (0 si égalité)
            $table->string('status')->default('completed'); // completed, tie
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_results');
    }
};

