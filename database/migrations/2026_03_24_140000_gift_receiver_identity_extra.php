<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gifts')) {
            return;
        }

        Schema::table('gifts', function (Blueprint $table) {
            if (! Schema::hasColumn('gifts', 'receiver_tracking_phone')) {
                $table->string('receiver_tracking_phone', 32)->nullable()->after('receiver');
            }
            if (! Schema::hasColumn('gifts', 'receiver_tracking_email')) {
                $table->string('receiver_tracking_email', 255)->nullable()->after('receiver_tracking_phone');
            }
            if (! Schema::hasColumn('gifts', 'receiver_identity_name')) {
                $table->string('receiver_identity_name', 120)->nullable()->after('receiver_tracking_email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gifts')) {
            return;
        }

        Schema::table('gifts', function (Blueprint $table) {
            foreach (['receiver_identity_name', 'receiver_tracking_email'] as $col) {
                if (Schema::hasColumn('gifts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
