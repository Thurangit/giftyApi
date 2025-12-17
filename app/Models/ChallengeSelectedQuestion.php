<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeSelectedQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'challenge_participant_id',
        'system_question_id',
        'question_order'
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function participant()
    {
        return $this->belongsTo(ChallengeParticipant::class);
    }

    public function systemQuestion()
    {
        return $this->belongsTo(SystemQuestion::class);
    }
}

