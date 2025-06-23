<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicianSkill extends Model
{
    use HasFactory;

    protected $table = 'technician_skills';

    protected $fillable = [
        'user_id',
        'skill_id',
    ];

    /**
     * The technician (user) this skill belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The skill related to this technician skill.
     */
    public function skill()
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
