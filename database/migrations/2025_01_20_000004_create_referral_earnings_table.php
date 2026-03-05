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
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_user_id')->constrained('users')->onDelete('cascade');
            $table->string('transaction_type'); // 'gift', 'quiz', 'moment', 'challenge'
            $table->unsignedBigInteger('transaction_id'); // ID de la transaction
            $table->decimal('transaction_amount', 10, 2);
            $table->decimal('earning_percentage', 5, 2); // Pourcentage gagné (10-100)
            $table->decimal('earning_amount', 10, 2); // Montant gagné
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index(['referrer_id', 'status']);
            $table->index(['transaction_type', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};

