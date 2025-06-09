<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'part_name',
        'serial_number',
        'is_available',
        'unit_type',
        'provided_by',
        'is_required',
        'is_provided',
        'added_by',
        'added_source',
        'notes',
        'price'

    ];


    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
