<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\WalletTransactionFactory> */
    use \App\Traits\HasSchoolScope, HasFactory, HasUlids;

    const PAYMENT_METHOD_GATEWAY = 'gateway';

    const PAYMENT_METHOD_CASH = 'cash';

    protected $fillable = [
        'school_id',
        'invoice_code',
        'student_id',
        'operator_id',
        'pic_user_id',
        'type',
        'category',
        'amount',
        'payment_fee',
        'admin_fee',
        'description',
        'payment_method',
        'status',
        'product_id',
    ];

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function scopeGateway($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_GATEWAY);
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_CASH);
    }

    public function items()
    {
        return $this->hasMany(WalletTransactionItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
