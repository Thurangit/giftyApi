<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Moment extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_name',
        'unique_link',
        'total_moments',
        'best_moment_order',
        'amount',
        'participant_phone',
        'opening_message',
        'status'
    ];

    public function items()
    {
        return $this->hasMany(MomentItem::class)->orderBy('moment_order');
    }

    public function attempts()
    {
        return $this->hasMany(MomentAttempt::class);
    }
}

