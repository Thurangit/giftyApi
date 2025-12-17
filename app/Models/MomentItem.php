<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MomentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'moment_id',
        'moment_description',
        'moment_order'
    ];

    public function moment()
    {
        return $this->belongsTo(Moment::class);
    }
}

