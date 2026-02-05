<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StudentReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'student_id',
        'semester_id',
        'school_id',
        'file_path',
        'uploaded_by',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFilesAttribute(): array
    {
        if (!$this->file_path) return [];
        
        $paths = json_decode($this->file_path, true);
        
        // Handle legacy single string format (backwards compatibility)
        if (!is_array($paths)) {
            $paths = [$this->file_path];
        }

        return collect($paths)->map(function ($path) {
            return Storage::url($path);
        })->toArray();
    }

    public function getFileUrlAttribute(): string
    {
        $files = $this->files;
        return !empty($files) ? $files[0] : '';
    }
}
