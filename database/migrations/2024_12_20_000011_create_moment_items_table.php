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
        Schema::create('moment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moment_id')->constrained()->onDelete('cascade');
            $table->text('moment_description'); // Description du moment
            $table->integer('moment_order'); // Ordre du moment (1 à total_moments)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moment_items');
    }
};

