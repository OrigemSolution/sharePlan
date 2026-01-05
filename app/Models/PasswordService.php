<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PasswordService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'logo',
        'price',
        'max_members',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'max_members' => 'integer',
    ];

    public function passwordSharingSlots(): HasMany
    {
        return $this->hasMany(PasswordSharingSlot::class);
    }
}
