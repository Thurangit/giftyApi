<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'participant_name',
        'participant_phone',
        'correct_answers',
        'total_questions',
        'score',
        'won_amount',
        'has_won',
        'status',
        'receiver_operator',
        'receiver_phone',
        'receiver_name',
        'receiver_email'
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function attemptAnswers()
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }
}

