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
        Schema::create('moments', function (Blueprint $table) {
            $table->id();
            $table->string('creator_name');
            $table->string('unique_link')->unique();
            $table->integer('total_moments'); // 3, 4, ou 5
            $table->integer('best_moment_order'); // Ordre du meilleur moment (1 à total_moments)
            $table->integer('amount'); // Montant à gagner
            $table->string('participant_phone')->nullable(); // Numéro de téléphone de la personne autorisée (optionnel)
            $table->text('opening_message')->nullable(); // Message d'ouverture (max 1000 caractères)
            $table->string('status')->default('active'); // active, completed, expired
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moments');
    }
};

