<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'winner_id',
        'creator_score',
        'participant_score',
        'total_amount',
        'won_amount',
        'status'
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function winner()
    {
        return $this->belongsTo(ChallengeParticipant::class, 'winner_id');
    }
}

