<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'area',
        'latitude',
        'longitude'
    ];

    // public function bookings()
    // {
    //     return $this->hasMany(Booking::class);
    // }
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }
}
