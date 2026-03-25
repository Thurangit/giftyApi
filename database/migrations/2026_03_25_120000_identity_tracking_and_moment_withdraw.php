<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quizzes') && ! Schema::hasColumn('quizzes', 'creator_phone')) {
            Schema::table('quizzes', function (Blueprint $table) {
                $table->string('creator_phone', 32)->nullable()->after('creator_email');
            });
        }

        if (Schema::hasTable('moments') && ! Schema::hasColumn('moments', 'creator_phone')) {
            Schema::table('moments', function (Blueprint $table) {
                $table->string('creator_phone', 32)->nullable()->after('creator_email');
            });
        }

        if (Schema::hasTable('gifts') && ! Schema::hasColumn('gifts', 'receiver_tracking_phone')) {
            Schema::table('gifts', function (Blueprint $table) {
                $table->string('receiver_tracking_phone', 32)->nullable()->after('receiver');
            });
        }

        if (Schema::hasTable('moment_attempts')) {
            Schema::table('moment_attempts', function (Blueprint $table) {
                if (! Schema::hasColumn('moment_attempts', 'receiver_operator')) {
                    $table->string('receiver_operator', 32)->nullable()->after('status');
                }
                if (! Schema::hasColumn('moment_attempts', 'receiver_phone')) {
                    $table->string('receiver_phone', 32)->nullable()->after('receiver_operator');
                }
                if (! Schema::hasColumn('moment_attempts', 'receiver_name')) {
                    $table->string('receiver_name', 120)->nullable()->after('receiver_phone');
                }
                if (! Schema::hasColumn('moment_attempts', 'receiver_email')) {
                    $table->string('receiver_email', 255)->nullable()->after('receiver_name');
                }
            });
        }

        if (Schema::hasTable('mymind_attempts')) {
            Schema::table('mymind_attempts', function (Blueprint $table) {
                if (! Schema::hasColumn('mymind_attempts', 'payout_phone')) {
                    $table->string('payout_phone', 32)->nullable()->after('partner_operator');
                }
                if (! Schema::hasColumn('mymind_attempts', 'payout_operator')) {
                    $table->string('payout_operator', 16)->nullable()->after('payout_phone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quizzes') && Schema::hasColumn('quizzes', 'creator_phone')) {
            Schema::table('quizzes', fn (Blueprint $t) => $t->dropColumn('creator_phone'));
        }
        if (Schema::hasTable('moments') && Schema::hasColumn('moments', 'creator_phone')) {
            Schema::table('moments', fn (Blueprint $t) => $t->dropColumn('creator_phone'));
        }
        if (Schema::hasTable('gifts') && Schema::hasColumn('gifts', 'receiver_tracking_phone')) {
            Schema::table('gifts', fn (Blueprint $t) => $t->dropColumn('receiver_tracking_phone'));
        }
        if (Schema::hasTable('moment_attempts')) {
            Schema::table('moment_attempts', function (Blueprint $table) {
                foreach (['receiver_operator', 'receiver_phone', 'receiver_name', 'receiver_email'] as $col) {
                    if (Schema::hasColumn('moment_attempts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('mymind_attempts')) {
            Schema::table('mymind_attempts', function (Blueprint $table) {
                foreach (['payout_phone', 'payout_operator'] as $col) {
                    if (Schema::hasColumn('mymind_attempts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
