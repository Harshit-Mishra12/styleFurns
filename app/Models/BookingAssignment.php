<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'status',           // 'assigned', 'rejected', etc.
        'reason',
        'assigned_at',
        'responded_at',
        'slot_date',
        'time_start',
        'time_end',
    ];

    protected $casts = [
        'assigned_at'   => 'datetime',
        'responded_at'  => 'datetime',
        'slot_date'     => 'date',
        'time_start'    => 'string',
        'time_end'      => 'string',
    ];


    protected $dates = ['assigned_at', 'responded_at'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
