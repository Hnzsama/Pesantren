<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Withdrawal extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'requested_by',
        'approved_by',
        'amount', // Simple withdrawal amount - NO FEES
        'bank_name',
        'account_number',
        'account_holder_name',
        'status',
        'locked_at',
        'requested_balance_snapshot',
        'requested_at',
        'approved_at',
        'rejected_at',
        'paid_at',
        'rejection_reason',
        'source_type', // REVENUE or TRUST_FUND
        'notes',
        'payment_proof', // Bukti transfer
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2', // Simple withdrawal amount
        'requested_balance_snapshot' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
        'locked_at' => 'datetime',
        'source_type' => 'string',
    ];

    /**
     * Get the school that owns the withdrawal.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user who requested the withdrawal.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the withdrawal.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if withdrawal is paid.
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'PAID';
    }

    /**
     * Check if withdrawal is pending.
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'REQUESTED';
    }

    /**
     * Check if withdrawal is approved.
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'APPROVED';
    }

    /**
     * Check if withdrawal is rejected.
     */
    public function getIsRejectedAttribute(): bool
    {
        return $this->status === 'REJECTED';
    }

    /**
     * Check if withdrawal is locked.
     */
    public function getIsLockedAttribute(): bool
    {
        return !is_null($this->locked_at);
    }

    /**
     * Scope to filter by school.
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get paid withdrawals.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'PAID');
    }

    /**
     * Scope to get pending withdrawals.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'REQUESTED');
    }
}
