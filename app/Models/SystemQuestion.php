<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'question',
        'correct_answer',
        'wrong_answers',
        'difficulty'
    ];

    protected $casts = [
        'wrong_answers' => 'array'
    ];
}

