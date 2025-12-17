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
        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('phone');
            $table->integer('amount'); // Montant misé par ce participant
            $table->enum('role', ['creator', 'participant']); // Rôle dans le challenge
            $table->boolean('has_selected_questions')->default(false); // A-t-il sélectionné ses questions
            $table->boolean('has_answered')->default(false); // A-t-il répondu aux questions
            $table->timestamps();

            // Un numéro de téléphone ne peut participer qu'une fois par challenge
            $table->unique(['challenge_id', 'phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_participants');
    }
};

