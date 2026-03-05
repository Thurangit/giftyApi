<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insérer les paramètres par défaut
        DB::table('admin_settings')->insert([
            [
                'key' => 'referral_earning_percentage_min',
                'value' => '10',
                'description' => 'Pourcentage minimum de gains sur les transactions pour les codes de parrainage (10-100%)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_earning_percentage_max',
                'value' => '100',
                'description' => 'Pourcentage maximum de gains sur les transactions pour les codes de parrainage (10-100%)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_referral_earning_percentage',
                'value' => '10',
                'description' => 'Pourcentage par défaut de gains sur les transactions pour les codes de parrainage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};

