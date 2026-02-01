<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VideoLecture extends Model
{
    use HasFactory, \App\Traits\HasSchoolScope;

    protected $guarded = [];

    protected $appends = ['thumbnail_url', 'embed_url'];

    protected $casts = [
        'published_at' => 'date',
    ];

    public function getEmbedUrlAttribute()
    {
        $videoId = $this->getVideoId();
        return $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
    }

    public function getThumbnailUrlAttribute()
    {
        $videoId = $this->getVideoId();
        return $videoId ? "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg" : null;
    }

    private function getVideoId()
    {
        $url = $this->youtube_url;
        
        // Handle standard youtube.com/watch?v=ID
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches)) {
            return $matches[1];
        }
        
        // Handle youtube.com/live/ID
        if (preg_match('/youtube\.com\/live\/([^"&?\/\s]{11})/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
