<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id', 'subscription_id', 'razorpay_order_id', 'razorpay_payment_id', 'amount', 'status', 'payment_response'
    ];

    /**
     * Relationship: A Transaction belongs to a User
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: A Transaction belongs to a Subscription
     */
    public function subscription() {
        return $this->belongsTo(Subscription::class);
    }
}
