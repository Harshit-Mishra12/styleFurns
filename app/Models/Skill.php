<?php
// app/Models/Skill.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'technician_skills');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'technician_skills');
    }
}
