<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    use HasFactory;

    protected $fillable = [
        "ref_one",
        "ref_two",
        "ref_three",
        "access_code",
        "name",
        "amount",
        "sender_opertor",
        "sender",
        "receiver_opertor",
        "receiver",
        "receiver_tracking_phone",
        "receiver_tracking_email",
        "receiver_identity_name",
        "message",
        "image",
        "email",
        "commentaire",
        "commentaire",
        "other_one",
        "other_two",
        "status"
    ];
}
