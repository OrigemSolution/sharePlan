<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'password_service_id',
        'slot_id',
        'password_sharing_slot_id',
        'amount',
        'reference',
        'status',
        'payment_channel',
        'currency',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }

    public function passwordSharingSlot()
    {
        return $this->belongsTo(PasswordSharingSlot::class, 'password_sharing_slot_id');
    }

    public function slotMember()
    {
        return $this->belongsTo(SlotMember::class, 'id', 'payment_id');
    }

    public function passwordService()
    {
        return $this->belongsTo(PasswordService::class, 'password_service_id');
    }

    public function passwordSharingSlotMember(): HasOne
    {
        return $this->hasOne(PasswordSharingSlotMember::class, 'payment_id');
    }
}

