<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'image_url',
        'type',         // 'before' or 'after'
        'uploaded_by'
    ];

    protected $casts = [
        'type' => 'string'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
