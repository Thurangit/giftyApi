<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_name',
        'creator_email',
        'unique_link',
        'access_code',
        'total_questions',
        'required_correct',
        'total_amount',
        'status',
        'access_type',
        'single_participant_phone',
        'opening_message'
    ];

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('question_order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function allowedParticipants()
    {
        return $this->hasMany(QuizAllowedParticipant::class);
    }
}

