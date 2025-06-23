<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slot extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'user_id',
        'current_members',
        'duration',
        'status',
        'is_active',
        'payment_status',
        'payment_reference'
    ];

    protected $casts = [
        'current_members' => 'integer',
        'duration' => 'integer',
        'is_active' => 'boolean',
        'payment_status' => 'string',
        'payment_reference' => 'string'
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SlotMember::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
