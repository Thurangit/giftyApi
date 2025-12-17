<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_question_id',
        'answer',
        'is_correct',
        'answer_order'
    ];

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}

