<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDeposit extends Model
{
    use HasFactory, HasUlids, \App\Traits\HasSchoolScope;

    protected $guarded = ['id'];

    protected $casts = [
        'deposited_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
