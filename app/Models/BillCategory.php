<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class BillCategory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['school_id', 'name', 'description'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
