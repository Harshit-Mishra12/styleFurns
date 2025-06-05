<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model {
    use HasFactory;

    protected $fillable = [
        'name', 'pricing', 'description', 'reward_type', 'reward_value'
    ];

    /**
     * Relationship: One Subscription can have many UserSubscriptions
     */
    public function userSubscriptions() {
        return $this->hasMany(UserSubscription::class);
    }
}
