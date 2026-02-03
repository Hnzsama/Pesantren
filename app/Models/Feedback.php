<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'sender_name',
        'email',
        'phone',
        'category',
        'message',
        'admin_reply',
        'replied_at',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'replied_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
