<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationHistory extends Model
{
    use HasFactory;

    protected $table = 'notification_history';

    protected $fillable = [
        'title',
        'body',
        'icon',
        'image',
        'recipient_type',
        'user_id',
        'url',
        'require_interaction',
        'sent_count'
    ];

    protected $casts = [
        'require_interaction' => 'boolean',
        'sent_count' => 'integer'
    ];

    /**
     * Relation avec l'utilisateur (si notification spécifique)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

