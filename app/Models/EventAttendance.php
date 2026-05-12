<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendance extends Model
{
    /** @use HasFactory<\Database\Factories\EventAttendanceFactory> */
    use HasFactory, HasUlids, \App\Traits\HasStudentSnapshot;

    protected $fillable = [
        'event_id',
        'student_id',
        'student_name',
        'student_nis',
        'check_in_time',
        'status',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
