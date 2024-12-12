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
        Schema::create('gift_refs', function (Blueprint $table) {
            $table->id();
            $table->string('ref');
            $table->integer('amount'); // Assurez-vous que 'amount' est un entier comme vous l'avez mentionné précédemment
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_refs');
    }
};
