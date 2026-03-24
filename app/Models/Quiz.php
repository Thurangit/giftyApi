<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_name',
        'creator_email',
        'unique_link',
        'access_code',
        'total_questions',
        'required_correct',
        'total_amount',
        'status',
        'access_type',
        'single_participant_phone',
        'opening_message',
        'challenge_mode',
        'challenge_intro',
        'challenge_creator_entry',
        'challenge_min_bet',
        'challenge_pot',
        'challenge_joins_count',
        'challenge_losers_count',
        'challenge_closed',
    ];

    protected $casts = [
        'challenge_mode' => 'boolean',
        'challenge_closed' => 'boolean',
    ];

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('question_order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function allowedParticipants()
    {
        return $this->hasMany(QuizAllowedParticipant::class);
    }

    public function challengeEntries()
    {
        return $this->hasMany(QuizChallengeEntry::class);
    }
}

