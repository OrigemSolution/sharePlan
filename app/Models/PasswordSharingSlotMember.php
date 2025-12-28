<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordSharingSlotMember extends Model
{
    use HasFactory;

    protected $table = 'password_sharing_slot_members';

    protected $fillable = [
        'password_sharing_slot_id',
        'user_id',
        'member_name',
        'member_email',
        'member_phone',
        'payment_status',
        'payment_id',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(PasswordSharingSlot::class, 'password_sharing_slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
