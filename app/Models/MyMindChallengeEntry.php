<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyMindChallengeEntry extends Model
{
    protected $fillable = [
        'mymind_game_id',
        'participant_phone',
        'participant_name',
        'stake_amount',
        'payment_reference',
        'status',
        'mymind_attempt_id',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(MyMindGame::class, 'mymind_game_id');
    }
}
