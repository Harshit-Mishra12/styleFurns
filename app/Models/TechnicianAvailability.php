<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TechnicianAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'technician_id',
        'date_time',
        'slot_count'
    ];

    protected $casts = [
        'date_time' => 'datetime'
    ];

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
