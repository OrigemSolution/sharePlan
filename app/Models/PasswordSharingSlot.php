<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PasswordSharingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'password_service_id',
        'user_id',
        'guest_limit',
        'current_members',
        'duration',
        'status',
        'payment_status',
        'payment_reference',
        'is_active'
    ];

    protected $casts = [
        'guest_limit' => 'integer',
        'current_members' => 'integer',
        'duration' => 'integer',
        'is_active' => 'boolean'
    ];

    public function passwordService(): BelongsTo
    {
        return $this->belongsTo(PasswordService::class, 'password_service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function passwordSharingSlotMembers(): HasMany
    {
        return $this->hasMany(PasswordSharingSlotMember::class, 'password_sharing_slot_id');
    }
}

