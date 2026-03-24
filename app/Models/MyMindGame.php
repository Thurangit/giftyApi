<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyMindGame extends Model
{
    use HasFactory;

    protected $table = 'mymind_games';

    protected $fillable = [
        'creator_name',
        'creator_email',
        'category',
        'questions_count',
        'final_amount',
        'opening_message',
        'unique_link',
        'payment_phone',
        'payment_operator',
        'payment_reference',
        'promo_code',
        'answers',
        'question_ids',
        'status',
        'access_code',
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
        'answers'      => 'array',
        'question_ids' => 'array',
        'final_amount' => 'integer',
        'questions_count' => 'integer',
        'challenge_mode' => 'boolean',
        'challenge_closed' => 'boolean',
    ];

    public function attempts()
    {
        return $this->hasMany(MyMindAttempt::class);
    }

    public function challengeEntries()
    {
        return $this->hasMany(MyMindChallengeEntry::class, 'mymind_game_id');
    }

    public function generateUniqueLink(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function generateAccessCode(): string
    {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    // Returns answers as a map: question_id => answer
    public function getAnswerMap(): array
    {
        $map = [];
        foreach ($this->answers as $item) {
            $map[$item['question_id']] = $item['answer'];
        }
        return $map;
    }
}
