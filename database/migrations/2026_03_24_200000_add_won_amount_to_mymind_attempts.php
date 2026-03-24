<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mymind_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('won_amount')->nullable()->after('won');
        });
    }

    public function down(): void
    {
        Schema::table('mymind_attempts', function (Blueprint $table) {
            $table->dropColumn('won_amount');
        });
    }
};
