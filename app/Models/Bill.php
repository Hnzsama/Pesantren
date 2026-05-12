<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    /** @use HasFactory<\Database\Factories\BillFactory> */
    use \App\Traits\HasSchoolScope, HasFactory, HasUlids, \App\Traits\HasStudentSnapshot;

    protected $fillable = [
        'school_id',
        'invoice_code',
        'student_id',
        'student_name',
        'student_nis',
        'student_class',
        'event_id',
        'bill_category_id',
        'title',
        'bill_code',
        'amount',
        'payment_fee',
        'admin_fee',
        'status',
        'due_date',
        'paid_at',
        'payment_method',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BillCategory::class, 'bill_category_id');
    }
}
