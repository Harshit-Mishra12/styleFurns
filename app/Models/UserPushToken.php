<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPushToken extends Model
{
    use HasFactory;

    protected $table = 'user_push_tokens';

    protected $fillable = [
        'user_id',
        'device_token',
    ];

    /**
     * Relationship to the User model.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
