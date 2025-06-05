<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserSubscription extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'status',
        'start_date',
        'end_date',
        'match_credits',
        'razorpay_payment_id',
        'amount_paid'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Relationship: A subscription belongs to a user.
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: A subscription is linked to a subscription plan.
     */
    public function subscription() {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get remaining days for Days Credit.
     */
    public function getDaysRemainingAttribute() {
        if ($this->end_date) {
            return max(0, Carbon::now()->diffInDays($this->end_date, false));
        }
        return 0;
    }

    /**
     * Check if subscription is active.
     */
    public function isActive() {
        if ($this->status === 'active') {
            // If it's a days-based subscription, check if it's still within the valid time range.
            if ($this->end_date) {
                return Carbon::now()->lessThan($this->end_date);
            }
            // If it's a match-based subscription, check if credits are left.
            if ($this->match_credits !== null && $this->match_credits > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Automatically update status when fetching.
     */
    protected static function boot() {
        parent::boot();

        static::retrieved(function ($subscription) {
            if ($subscription->status === 'active') {
                if ($subscription->end_date && Carbon::now()->greaterThan($subscription->end_date)) {
                    $subscription->status = 'expired';
                    $subscription->save();
                } elseif ($subscription->match_credits !== null && $subscription->match_credits <= 0) {
                    $subscription->status = 'expired';
                    $subscription->save();
                }
            }
        });
    }
}
