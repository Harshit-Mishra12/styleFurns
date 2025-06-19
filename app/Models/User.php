<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'mobile',
        'password',
        'job_status',
        'profile_picture',
        'role',
        'active',
        'verification_uid',
        'otp',
        'otp_expires_at',
        'is_verified'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp'
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'is_verified' => 'boolean'
    ];

    /**
     * Get the user's bank details.
     */
    public function bankDetails()
    {
        return $this->hasOne(UserBankDetails::class, 'user_id');
    }

    public function technicianArea()
    {
        return $this->hasOne(TechnicianArea::class, 'user_id');
    }

    // User.php
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'technician_skills', 'user_id', 'skill_id');
    }


    public function bookings()
    {
        return $this->hasMany(Booking::class, 'current_technician_id');
    }

    public function technicianAreas()
    {
        return $this->hasMany(TechnicianArea::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
