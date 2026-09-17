<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A daily transport + airtime facilitation request from an RM or
 * Supervisor - see the create_requisitions_table migration for why
 * transport and airtime are tracked as two independent tracks rather than
 * one combined request/approval.
 */
class Requisition extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const DEFAULT_TRANSPORT_AMOUNT = 1500;

    public const DEFAULT_AIRTIME_AMOUNT = 250;

    protected $fillable = [
        'requester_id',
        'institution_visiting',
        'working_day',
        'transport_requested_at',
        'transport_amount_requested',
        'transport_status',
        'transport_approved_by_id',
        'transport_approved_at',
        'transport_paid_amount',
        'airtime_requested_at',
        'airtime_amount_requested',
        'airtime_status',
        'airtime_approved_by_id',
        'airtime_approved_at',
        'airtime_paid_amount',
    ];

    protected function casts(): array
    {
        return [
            'working_day' => 'date',
            'transport_requested_at' => 'datetime',
            'transport_amount_requested' => 'decimal:2',
            'transport_approved_at' => 'datetime',
            'transport_paid_amount' => 'decimal:2',
            'airtime_requested_at' => 'datetime',
            'airtime_amount_requested' => 'decimal:2',
            'airtime_approved_at' => 'datetime',
            'airtime_paid_amount' => 'decimal:2',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function transportApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transport_approved_by_id');
    }

    public function airtimeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'airtime_approved_by_id');
    }

    public function transportBalance(): float
    {
        return (float) $this->transport_amount_requested - (float) $this->transport_paid_amount;
    }

    public function airtimeBalance(): float
    {
        return (float) $this->airtime_amount_requested - (float) $this->airtime_paid_amount;
    }

    public function totalRequestedForDay(): float
    {
        return (float) $this->transport_amount_requested + (float) $this->airtime_amount_requested;
    }

    public function totalPaidForDay(): float
    {
        return (float) $this->transport_paid_amount + (float) $this->airtime_paid_amount;
    }

    public function totalBalanceForDay(): float
    {
        return $this->transportBalance() + $this->airtimeBalance();
    }
}
