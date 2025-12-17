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
        Schema::create('moment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moment_id')->constrained()->onDelete('cascade');
            $table->string('participant_name');
            $table->string('participant_phone')->nullable(); // Optionnel si le moment n'en nécessite pas
            $table->integer('selected_moment_order'); // Ordre du moment sélectionné
            $table->boolean('has_won')->default(false);
            $table->integer('won_amount')->default(0);
            $table->string('status')->default('completed');
            $table->timestamps();

            // Une personne ne peut participer qu'une seule fois (seulement si un numéro est fourni)
            // Note: On ne peut pas créer une contrainte unique avec nullable, donc on gère ça dans le code
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moment_attempts');
    }
};

