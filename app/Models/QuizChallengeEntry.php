<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizChallengeEntry extends Model
{
    protected $fillable = [
        'quiz_id',
        'participant_phone',
        'participant_name',
        'stake_amount',
        'payment_reference',
        'status',
        'quiz_attempt_id',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
