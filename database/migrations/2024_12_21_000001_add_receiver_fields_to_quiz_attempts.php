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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->string('receiver_operator')->nullable()->after('status');
            $table->string('receiver_phone')->nullable()->after('receiver_operator');
            $table->string('receiver_name')->nullable()->after('receiver_phone');
            $table->string('receiver_email')->nullable()->after('receiver_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['receiver_operator', 'receiver_phone', 'receiver_name', 'receiver_email']);
        });
    }
};

