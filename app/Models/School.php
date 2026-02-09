<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'address', 'phone', 'email', 'website', 'theme_color', 'logo_path', 'profile_photo_path'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
