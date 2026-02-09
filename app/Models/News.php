<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    use HasFactory, HasUlids, \App\Traits\HasSchoolScope;

    protected $fillable = [
        'school_id',
        'author_id',
        'title',
        'content',
        'images',
        'target_role',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'images' => 'array',
    ];

    /**
     * Get the author of the news.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the school associated with the news.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
