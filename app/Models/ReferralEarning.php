<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'transaction_type',
        'transaction_id',
        'transaction_amount',
        'earning_percentage',
        'earning_amount',
        'status',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'earning_percentage' => 'decimal:2',
        'earning_amount' => 'decimal:2',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}

