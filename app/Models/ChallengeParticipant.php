<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'name',
        'phone',
        'amount',
        'role',
        'has_selected_questions',
        'has_answered'
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function selectedQuestions()
    {
        return $this->hasMany(ChallengeSelectedQuestion::class);
    }

    public function answers()
    {
        return $this->hasMany(ChallengeAnswer::class);
    }
}

