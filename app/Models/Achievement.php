<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use \App\Traits\HasSchoolScope;
    protected $guarded = [];

    protected $casts = [
        'date_achieved' => 'date',
        'images' => 'array',
    ];

    protected $appends = ['image_urls'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getImageUrlsAttribute()
    {
        if (empty($this->images)) {
            return [];
        }

        return collect($this->images)->map(function ($image) {
            return asset('storage/' . $image);
        })->toArray();
    }
}
