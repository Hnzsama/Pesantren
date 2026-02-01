<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SowanSchedule extends Model
{
    use HasFactory, \App\Traits\HasSchoolScope;

    protected $guarded = [];

    protected $casts = [
        'request_date' => 'datetime',
    ];
}
