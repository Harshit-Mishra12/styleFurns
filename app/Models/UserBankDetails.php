<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBankDetails extends Model
{
    use HasFactory;

    protected $table = 'user_bank_details';

    protected $fillable = [
        'user_id',
        'account_name',
        'account_number',
        'ifsc_code',
    ];

    /**
     * Get the user that owns the bank details.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
