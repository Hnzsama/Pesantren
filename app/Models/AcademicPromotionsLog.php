<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class AcademicPromotionsLog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'academic_promotions_log'; // Explicit table name if needed

    protected $guarded = ['id'];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fromAcademicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    public function toAcademicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function fromLevel()
    {
        return $this->belongsTo(Level::class, 'from_level_id');
    }

    public function toLevel()
    {
        return $this->belongsTo(Level::class, 'to_level_id');
    }
}
