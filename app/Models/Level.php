<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    /** @use HasFactory<\Database\Factories\LevelFactory> */
    use HasFactory, \App\Traits\HasSchoolScope;

    protected $fillable = ['name', 'alias', 'school_id', 'department_id', 'grade_level'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }


    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function getBadgeColorAttribute(): string
    {
        return match ($this->alias) {
            'PAUD' => 'orange',
            'TK' => 'amber',
            'MDT' => 'lime',
            'MI' => 'emerald',
            'MTs' => 'teal',
            'MA' => 'cyan',
            'Kuliah' => 'sky',
            'Ma\'had Aly' => 'indigo',
            // 'Lainnya' maps to default
            default => 'zinc',
        };
    }
}
