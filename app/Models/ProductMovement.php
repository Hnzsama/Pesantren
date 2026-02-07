<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMovement extends Model
{
    use HasFactory, HasUlids, \App\Traits\HasSchoolScope;

    protected $fillable = [
        'school_id',
        'product_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'source',
        'reference_id',
        'note',
        'pic_user_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}
