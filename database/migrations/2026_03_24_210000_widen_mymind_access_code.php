<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mymind_games') && Schema::hasColumn('mymind_games', 'access_code')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE mymind_games MODIFY access_code VARCHAR(24) NULL');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mymind_games') && Schema::hasColumn('mymind_games', 'access_code')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE mymind_games MODIFY access_code VARCHAR(8) NULL');
            }
        }
    }
};
