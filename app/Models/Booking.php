<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'damage_desc',
        'scheduled_date',
        'status',
        'current_technician_id',
        'slots_required',
        'price',
        'customer_id',
        'completed_at',
        'is_active',
        'remark',
        'status_comment',
        'required_skills',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at'   => 'datetime',
        'is_active'      => 'boolean',
        'required_skills' => 'array', // 🔁 Casts JSON to PHP array
    ];



    protected $dates = ['scheduled_date', 'completed_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'current_technician_id');
    }


    public function technicianHistory()
    {
        return $this->hasMany(BookingAssignment::class)->with('user');
    }


    public function assignments()
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function parts()
    {
        return $this->hasMany(BookingPart::class);
    }

    public function images()
    {
        return $this->hasMany(BookingImage::class);
    }
    public function currentAssignment()
    {
        return $this->hasOne(BookingAssignment::class)
            ->where('status', 'assigned')
            ->latestOfMany('assigned_at');
    }


    // public function incompleteReason()
    // {
    //     return $this->hasOne(BookingIncompleteReason::class);
    // }
}
