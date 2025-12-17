<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAllowedParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'phone_number'
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}

