<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MomentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'moment_id',
        'participant_name',
        'participant_phone',
        'selected_moment_order',
        'has_won',
        'won_amount',
        'status'
    ];

    public function moment()
    {
        return $this->belongsTo(Moment::class);
    }
}

