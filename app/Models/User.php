<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar',
        'bio',
        'balance',
        'status',
        'role',
        'referral_code',
        'referred_by',
        'eyamo_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Quizzes created by the user
     */
    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'creator_id');
    }

    /**
     * Moments created by the user
     */
    public function moments()
    {
        return $this->hasMany(Moment::class, 'creator_id');
    }

    /**
     * Challenges participated in
     */
    public function challenges()
    {
        return $this->hasMany(ChallengeParticipant::class, 'user_id');
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'AdminisThuran';
    }

    /**
     * Utilisateur qui a parrainé cet utilisateur
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Utilisateurs parrainés par cet utilisateur
     */
    public function referredUsers()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Codes promo créés par l'utilisateur
     */
    public function promoCodes()
    {
        return $this->hasMany(PromoCode::class, 'created_by');
    }

    /**
     * Gains de parrainage de cet utilisateur
     */
    public function referralEarnings()
    {
        return $this->hasMany(ReferralEarning::class, 'referrer_id');
    }
}
