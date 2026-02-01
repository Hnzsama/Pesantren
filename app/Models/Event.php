<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, HasUlids, \App\Traits\HasSchoolScope;

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'is_paid',
        'amount',
        'participation_type',
        'school_id',
    ];

    protected $guarded = ['id'];

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    protected $casts = [
        'event_date' => 'date',
        'is_paid' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function levels()
    {
        return $this->belongsToMany(Level::class, 'event_level');
    }
}
