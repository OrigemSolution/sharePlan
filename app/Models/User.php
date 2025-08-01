<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'whatsapp_phone',
        'phone',
        'bank',
        'account_no',
        'account_name',
        'password',
        'status',
        'role_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function slots()
    {
        return $this->hasMany(Slot::class, 'user_id');
    }

    public function slotMemberships()
    {
        return $this->hasMany(SlotMember::class, 'member_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function creatorPayouts()
    {
        return $this->hasMany(CreatorPayout::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function socialMedia()
    {
        return $this->hasMany(SocialMedia::class);
    }

    public function isAdmin()
    {
        return $this->role_id === 2;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
        // return str_ends_with($this->email, '@extensionafrica.com');
    }
}
