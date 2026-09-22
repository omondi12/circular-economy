<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionPayment extends Model
{
    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_AIRTIME = 'airtime';

    public const STATUS_INITIATING = 'initiating';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING_RECONCILIATION = 'pending_reconciliation';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'requisition_id',
        'initiated_by_id',
        'category',
        'amount_minor',
        'phone_number',
        'provider',
        'status',
        'idempotency_key',
        'nawiri_payment_id',
        'provider_reference',
        'provider_order_id',
        'failure_reason',
        'provider_response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'provider_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_INITIATING,
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_RECONCILIATION,
        ], true);
    }

    public function amount(): float
    {
        return $this->amount_minor / 100;
    }
}
