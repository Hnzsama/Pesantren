<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\HasSchoolScope;

class Activity extends Model
{
    use HasFactory, HasUlids, HasSchoolScope;

    protected $fillable = ['school_id', 'level_id', 'name', 'type', 'description'];

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    public function level()
    {
        return $this->belongsTo(\App\Models\Level::class);
    }

    public function attendances()
    {
        return $this->hasMany(ActivityAttendance::class);
    }
}
