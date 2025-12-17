<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class embassadorsGift extends Model
{
    use HasFactory;
    protected $fillable = ["transaction", "code", "amount", "type", "status"];
}
