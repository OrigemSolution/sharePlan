<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'message',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];
}
