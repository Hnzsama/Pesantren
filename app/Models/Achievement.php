<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    use HasFactory, HasUlids, \App\Traits\HasStudentSnapshot;

    protected $fillable = [
        'title',
        'school_id',
        'student_id',
        'student_name',
        'student_nis',
        'date_achieved',
        'description',
        'images',
    ];

    protected $casts = [
        'date_achieved' => 'date',
        'images' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
