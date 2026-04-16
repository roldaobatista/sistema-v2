<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'description',
    ];

    public function requirements()
    {
        return $this->hasMany(SkillRequirement::class);
    }

    public function userSkills()
    {
        return $this->hasMany(UserSkill::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_skills')
            ->withPivot('required_level')
            ->withTimestamps();
    }
}
