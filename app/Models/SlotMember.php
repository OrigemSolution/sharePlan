<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'slot_id',
        'user_id',
        'member_name',
        'member_email',
        'member_phone',
        'payment_status',
        'payment_id'
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
