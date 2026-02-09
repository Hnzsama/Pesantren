<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SowanSchedule extends Model
{
    use HasFactory, \App\Traits\HasSchoolScope, \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $guarded = [];

    protected $casts = [
        'request_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
