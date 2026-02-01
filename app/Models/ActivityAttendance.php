<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\HasSchoolScope;

class ActivityAttendance extends Model
{
    use HasFactory, HasUlids, HasSchoolScope;

    protected $fillable = ['school_id', 'student_id', 'activity_id', 'date', 'status', 'remarks'];

    protected $casts = [
        'date' => 'date',
    ];

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
