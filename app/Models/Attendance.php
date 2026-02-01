<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceFactory> */
    use HasFactory, HasUlids, \App\Traits\HasSchoolScope;

    protected $fillable = [
        'student_id',
        'school_id',
        'date',
        'clock_in',
        'status',
    ];

    protected $guarded = ['id'];

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    protected $casts = [
        'date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
