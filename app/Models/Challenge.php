<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_link',
        'access_code',
        'creator_name',
        'creator_phone',
        'creator_amount',
        'amount_rule',
        'total_questions',
        'status'
    ];

    public function participants()
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function creator()
    {
        return $this->hasOne(ChallengeParticipant::class)->where('role', 'creator');
    }

    public function participant()
    {
        return $this->hasOne(ChallengeParticipant::class)->where('role', 'participant');
    }

    public function selectedQuestions()
    {
        return $this->hasMany(ChallengeSelectedQuestion::class);
    }

    public function results()
    {
        return $this->hasOne(ChallengeResult::class);
    }
}

