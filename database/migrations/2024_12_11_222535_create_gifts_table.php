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
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->string('ref_one');
            $table->string('ref_two')->nullable();
            $table->string('ref_three')->nullable();
            $table->string('name')->nullable();
            $table->integer('amount'); // Changement de decimal à integer
            $table->string('sender_opertor');
            $table->string('sender');
            $table->string('receiver_opertor')->nullable();
            $table->string('receiver')->nullable();
            $table->text('message')->nullable();
            $table->string('image')->nullable();
            $table->string('email')->nullable();
            $table->text('commentaire')->nullable();
            $table->string('other_one')->nullable();
            $table->string('other_two')->nullable();
            $table->string('status');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
