<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TechnicianArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'area',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];


    public function technician()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
