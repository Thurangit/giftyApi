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
        Schema::create('embassadors_gifts', function (Blueprint $table) {
            $table->id();
            $table->text('transaction')->nullable();
            $table->text('code')->nullable();
            $table->string('amount')->nullable();
            $table->string('type')->nullable();
            $table->text('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embassadors_gifts');
    }
};
