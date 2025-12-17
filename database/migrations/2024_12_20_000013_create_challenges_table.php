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
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('unique_link')->unique();
            $table->string('creator_name');
            $table->string('creator_phone');
            $table->integer('creator_amount'); // Montant du créateur
            $table->enum('amount_rule', ['equal_or_more', 'less']); // Règle pour le montant du participant
            $table->integer('total_questions'); // 10, 15, 20, 25, 30
            $table->string('status')->default('waiting_participant'); // waiting_participant, waiting_questions, active, completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};

