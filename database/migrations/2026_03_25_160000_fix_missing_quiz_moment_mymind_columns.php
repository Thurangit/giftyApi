<?php

/**
 * Répare les environnements où les migrations 2026_03_24_100000 / 2026_03_25_120000
 * n’ont pas été exécutées (ex. échec antérieur sur une autre migration).
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quizzes')) {
            Schema::table('quizzes', function (Blueprint $table) {
                if (! Schema::hasColumn('quizzes', 'creator_phone')) {
                    if (Schema::hasColumn('quizzes', 'creator_email')) {
                        $table->string('creator_phone', 32)->nullable()->after('creator_email');
                    } else {
                        $table->string('creator_phone', 32)->nullable();
                    }
                }
                foreach ([
                    'challenge_mode' => fn () => $table->boolean('challenge_mode')->default(false),
                    'challenge_intro' => fn () => $table->text('challenge_intro')->nullable(),
                    'challenge_creator_entry' => fn () => $table->unsignedInteger('challenge_creator_entry')->default(0),
                    'challenge_min_bet' => fn () => $table->unsignedInteger('challenge_min_bet')->default(0),
                    'challenge_pot' => fn () => $table->unsignedBigInteger('challenge_pot')->default(0),
                    'challenge_joins_count' => fn () => $table->unsignedInteger('challenge_joins_count')->default(0),
                    'challenge_losers_count' => fn () => $table->unsignedInteger('challenge_losers_count')->default(0),
                    'challenge_closed' => fn () => $table->boolean('challenge_closed')->default(false),
                ] as $col => $add) {
                    if (! Schema::hasColumn('quizzes', $col)) {
                        $add();
                    }
                }
            });
        }

        if (Schema::hasTable('moments') && ! Schema::hasColumn('moments', 'creator_phone')) {
            Schema::table('moments', function (Blueprint $table) {
                if (Schema::hasColumn('moments', 'creator_email')) {
                    $table->string('creator_phone', 32)->nullable()->after('creator_email');
                } else {
                    $table->string('creator_phone', 32)->nullable();
                }
            });
        }

        if (Schema::hasTable('mymind_games')) {
            Schema::table('mymind_games', function (Blueprint $table) {
                foreach ([
                    'challenge_mode' => fn () => $table->boolean('challenge_mode')->default(false),
                    'challenge_intro' => fn () => $table->text('challenge_intro')->nullable(),
                    'challenge_creator_entry' => fn () => $table->unsignedInteger('challenge_creator_entry')->default(0),
                    'challenge_min_bet' => fn () => $table->unsignedInteger('challenge_min_bet')->default(0),
                    'challenge_pot' => fn () => $table->unsignedBigInteger('challenge_pot')->default(0),
                    'challenge_joins_count' => fn () => $table->unsignedInteger('challenge_joins_count')->default(0),
                    'challenge_losers_count' => fn () => $table->unsignedInteger('challenge_losers_count')->default(0),
                    'challenge_closed' => fn () => $table->boolean('challenge_closed')->default(false),
                ] as $col => $add) {
                    if (! Schema::hasColumn('mymind_games', $col)) {
                        $add();
                    }
                }
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

        if (! Schema::hasTable('quiz_challenge_entries')) {
            Schema::create('quiz_challenge_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
                $table->string('participant_phone', 32);
                $table->string('participant_name')->nullable();
                $table->unsignedInteger('stake_amount');
                $table->string('payment_reference')->nullable();
                $table->string('status')->default('paid');
                $table->unsignedBigInteger('quiz_attempt_id')->nullable();
                $table->timestamps();
                $table->unique(['quiz_id', 'participant_phone']);
            });
        }

        if (! Schema::hasTable('mymind_challenge_entries')) {
            Schema::create('mymind_challenge_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mymind_game_id')->constrained('mymind_games')->cascadeOnDelete();
                $table->string('participant_phone', 32);
                $table->string('participant_name')->nullable();
                $table->unsignedInteger('stake_amount');
                $table->string('payment_reference')->nullable();
                $table->string('status')->default('paid');
                $table->unsignedBigInteger('mymind_attempt_id')->nullable();
                $table->timestamps();
                $table->unique(['mymind_game_id', 'participant_phone']);
            });
        }
    }

    public function down(): void
    {
        // Pas de rollback destructif automatique — schéma partiellement inconnu.
    }
};
