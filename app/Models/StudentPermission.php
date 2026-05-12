<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPermission extends Model
{
    use HasFactory, \App\Traits\HasSchoolScope, \Illuminate\Database\Eloquent\Concerns\HasUlids, \App\Traits\HasStudentSnapshot;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(\App\Models\Student::class);
    }
}
