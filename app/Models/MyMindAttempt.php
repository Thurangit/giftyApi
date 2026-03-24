<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyMindAttempt extends Model
{
    use HasFactory;

    protected $table = 'mymind_attempts';

    protected $fillable = [
        'mymind_game_id',
        'partner_phone',
        'partner_operator',
        'answers',
        'score',
        'total_questions',
        'won',
        'won_amount',
        'payment_reference',
        'prize_withdrawn',
    ];

    protected $casts = [
        'answers'         => 'array',
        'score'           => 'integer',
        'total_questions' => 'integer',
        'won'             => 'boolean',
        'won_amount'      => 'integer',
        'prize_withdrawn' => 'boolean',
    ];

    public function game()
    {
        return $this->belongsTo(MyMindGame::class, 'mymind_game_id');
    }
}
