<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPredictionMessage extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'match_id',
        'question_id',
        'admin_reply'
    ];

    /**
     * Relationships
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function match() {
        return $this->belongsTo(Matches::class);
    }

    public function question() {
        return $this->belongsTo(PredictionQuestion::class, 'question_id');
    }
}
