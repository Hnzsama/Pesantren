<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasUlids;

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('grade_level');
    }

    public function getBadgeColorAttribute(): string
    {
        $code = strtoupper($this->code);

        return match (true) {
            str_starts_with($code, 'PAUD') => 'orange',
            str_starts_with($code, 'TK') || str_starts_with($code, 'RA') => 'amber',
            str_starts_with($code, 'MDT') => 'lime',
            str_starts_with($code, 'MI') || str_starts_with($code, 'SD') => 'emerald',
            str_starts_with($code, 'MTS') || str_starts_with($code, 'SMP') => 'teal',
            str_starts_with($code, 'MA') || str_starts_with($code, 'SMA') || str_starts_with($code, 'SMK') => 'cyan',
            str_starts_with($code, 'KUL') => 'sky',
            str_starts_with($code, 'MA\'HAD') => 'indigo',
            default => 'zinc',
        };
    }
}
