<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictionQuestion extends Model {
    use HasFactory;

    protected $fillable = ['question_text', 'status'];

    /**
     * Relationship: A PredictionQuestion can have many UserPredictionMessages
     */
    public function userPredictionMessages() {
        return $this->hasMany(UserPredictionMessage::class);
    }
}
