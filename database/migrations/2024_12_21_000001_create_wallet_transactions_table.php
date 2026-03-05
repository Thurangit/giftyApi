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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['deposit', 'withdrawal', 'refund']); // deposit = ajout, withdrawal = retrait, refund = remboursement
            $table->enum('source_type', ['gift', 'quiz', 'moment', 'challenge', 'manual']); // Type de source
            $table->unsignedBigInteger('source_id')->nullable(); // ID de la source (gift_id, quiz_id, etc.)
            $table->string('source_ref')->nullable(); // Référence de la source (ref_one pour gift, unique_link pour quiz, etc.)
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};

