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
        // Modifier la colonne participant_phone dans moments
        if (Schema::hasTable('moments') && Schema::hasColumn('moments', 'participant_phone')) {
            try {
                DB::statement('ALTER TABLE `moments` MODIFY `participant_phone` VARCHAR(20) NULL');
            } catch (\Exception $e) {
                // La colonne est peut-être déjà nullable, on continue
            }
        }

        // Pour moment_attempts, on doit d'abord supprimer l'index unique manuellement
        // car MySQL peut avoir des problèmes avec les index uniques sur des colonnes nullable
        if (Schema::hasTable('moment_attempts')) {
            // Vérifier si l'index existe avant de le supprimer
            $indexes = DB::select("SHOW INDEX FROM moment_attempts WHERE Key_name = 'moment_attempts_moment_id_participant_phone_unique'");
            if (count($indexes) > 0) {
                try {
                    // Supprimer l'index avec une requête SQL directe
                    DB::statement('ALTER TABLE `moment_attempts` DROP INDEX `moment_attempts_moment_id_participant_phone_unique`');
                } catch (\Exception $e) {
                    // Si on ne peut pas supprimer l'index, on essaie une autre approche
                    // On peut ignorer cette erreur car la colonne peut déjà être nullable
                }
            }

            // Maintenant on peut modifier la colonne
            if (Schema::hasColumn('moment_attempts', 'participant_phone')) {
                try {
                    DB::statement('ALTER TABLE `moment_attempts` MODIFY `participant_phone` VARCHAR(20) NULL');
                } catch (\Exception $e) {
                    // La colonne est peut-être déjà nullable, on continue
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->string('participant_phone')->nullable(false)->change();
        });

        Schema::table('moment_attempts', function (Blueprint $table) {
            $table->string('participant_phone')->nullable(false)->change();
        });

        // Recréer l'index unique seulement si nécessaire
        // Note: On ne peut pas créer un index unique avec nullable, donc on le fait seulement si on rend non-nullable
        try {
            Schema::table('moment_attempts', function (Blueprint $table) {
                $table->unique(['moment_id', 'participant_phone']);
            });
        } catch (\Exception $e) {
            // Ignorer si l'index existe déjà
        }
    }
};

