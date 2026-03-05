<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'source_type',
        'source_id',
        'source_ref',
        'amount',
        'description',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir la source associée
     */
    public function source()
    {
        switch ($this->source_type) {
            case 'gift':
                return $this->belongsTo(Gift::class, 'source_id');
            case 'quiz':
                return $this->belongsTo(Quiz::class, 'source_id');
            case 'moment':
                return $this->belongsTo(Moment::class, 'source_id');
            case 'challenge':
                return $this->belongsTo(Challenge::class, 'source_id');
            default:
                return null;
        }
    }
}

