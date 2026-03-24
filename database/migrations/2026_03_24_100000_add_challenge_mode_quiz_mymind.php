<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('challenge_mode')->default(false)->after('opening_message');
            $table->text('challenge_intro')->nullable()->after('challenge_mode');
            $table->unsignedInteger('challenge_creator_entry')->default(0)->after('challenge_intro');
            $table->unsignedInteger('challenge_min_bet')->default(0)->after('challenge_creator_entry');
            $table->unsignedBigInteger('challenge_pot')->default(0)->after('challenge_min_bet');
            $table->unsignedInteger('challenge_joins_count')->default(0)->after('challenge_pot');
            $table->unsignedInteger('challenge_losers_count')->default(0)->after('challenge_joins_count');
            $table->boolean('challenge_closed')->default(false)->after('challenge_losers_count');
        });

        Schema::table('mymind_games', function (Blueprint $table) {
            $table->boolean('challenge_mode')->default(false)->after('access_code');
            $table->text('challenge_intro')->nullable()->after('challenge_mode');
            $table->unsignedInteger('challenge_creator_entry')->default(0)->after('challenge_intro');
            $table->unsignedInteger('challenge_min_bet')->default(0)->after('challenge_creator_entry');
            $table->unsignedBigInteger('challenge_pot')->default(0)->after('challenge_min_bet');
            $table->unsignedInteger('challenge_joins_count')->default(0)->after('challenge_pot');
            $table->unsignedInteger('challenge_losers_count')->default(0)->after('challenge_joins_count');
            $table->boolean('challenge_closed')->default(false)->after('challenge_losers_count');
        });

        Schema::create('quiz_challenge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->string('participant_phone', 32);
            $table->string('participant_name')->nullable();
            $table->unsignedInteger('stake_amount');
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('paid'); // paid | completed
            $table->unsignedBigInteger('quiz_attempt_id')->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'participant_phone']);
        });

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

    public function down(): void
    {
        Schema::dropIfExists('mymind_challenge_entries');
        Schema::dropIfExists('quiz_challenge_entries');

        Schema::table('mymind_games', function (Blueprint $table) {
            $table->dropColumn([
                'challenge_mode', 'challenge_intro', 'challenge_creator_entry', 'challenge_min_bet',
                'challenge_pot', 'challenge_joins_count', 'challenge_losers_count', 'challenge_closed',
            ]);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn([
                'challenge_mode', 'challenge_intro', 'challenge_creator_entry', 'challenge_min_bet',
                'challenge_pot', 'challenge_joins_count', 'challenge_losers_count', 'challenge_closed',
            ]);
        });
    }
};
