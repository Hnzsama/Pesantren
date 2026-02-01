<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory, HasUlids, \App\Traits\HasSchoolScope;

    public function getBarcodeSvgAttribute(): string
    {
        $generator = new \Picqer\Barcode\BarcodeGeneratorSVG;

        return $generator->getBarcode($this->nis ?? '000000', $generator::TYPE_CODE_128);
    }

    public function getQrCodeUrlAttribute(): string
    {
        $data = urlencode($this->nis ?? '000000');
        // Fallback to high-quality online QR generator
        return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={$data}&format=svg";
    }

    public function getPhotoUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // Generate absolute URL using asset() to include APP_URL
        return asset(\Illuminate\Support\Facades\Storage::disk('public')->url($value));
    }

    protected $fillable = [
        'nis',
        'full_name',
        'parent_user_id',
        'wallet_balance',
        'status',
        'gender',
        'photo_url',
        'level_id',
        'school_id',
        'wallet_pin',
        'daily_cash_limit',
        'allow_transaction',
    ];

    protected $guarded = ['id'];

    protected $hidden = [
        'wallet_pin',
    ];

    protected $casts = [
        'daily_cash_limit' => 'decimal:2',
        'allow_transaction' => 'boolean',
        'birth_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function department()
    {
        return $this->hasOneThrough(Department::class, Level::class, 'id', 'id', 'level_id', 'department_id');
    }

    public function academicHistory(): HasMany
    {
        return $this->hasMany(StudentAcademic::class)->orderBy('created_at', 'desc');
    }

    public function wallet_transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function event_attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    /**
     * Check if student can perform transactions
     */
    public function canTransact(): bool
    {
        return $this->allow_transaction;
    }

    /**
     * Check if student has set up their PIN
     */
    public function hasPin(): bool
    {
        return !empty($this->wallet_pin);
    }
    public function reports(): HasMany
    {
        return $this->hasMany(StudentReport::class);
    }

    public function card(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentCard::class);
    }

}
