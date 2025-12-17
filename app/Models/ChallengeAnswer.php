<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'challenge_participant_id',
        'challenge_selected_question_id',
        'selected_answer',
        'is_correct'
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function participant()
    {
        return $this->belongsTo(ChallengeParticipant::class);
    }

    public function selectedQuestion()
    {
        return $this->belongsTo(ChallengeSelectedQuestion::class);
    }
}

