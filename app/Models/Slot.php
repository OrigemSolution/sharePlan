<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'user_id',
        'current_members',
        'duration',
        'status',
        'expires_at'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members()
    {
        return $this->hasMany(SlotMember::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
