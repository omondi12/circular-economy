<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'recipient_phone_numbers',
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
            'recipient_phone_numbers' => 'array',
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

    public function payments(): HasMany
    {
        return $this->hasMany(RequisitionPayment::class);
    }

    public function latestPayment(string $category): ?RequisitionPayment
    {
        return $this->payments
            ->where('category', $category)
            ->sortByDesc('id')
            ->first();
    }

    public function activePayment(string $category, ?string $phoneNumber = null): ?RequisitionPayment
    {
        return $this->payments
            ->where('category', $category)
            ->when($phoneNumber !== null, fn ($payments) => $payments->where('phone_number', $phoneNumber))
            ->filter(fn (RequisitionPayment $payment) => $payment->isActive())
            ->sortByDesc('id')
            ->first();
    }

    public function recipientPhoneNumbers(): array
    {
        $numbers = collect($this->recipient_phone_numbers ?? [])
            ->map(fn ($phone) => trim((string) $phone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($numbers === [] && $this->requester?->phone_number) {
            return [$this->requester->phone_number];
        }

        return $numbers;
    }

    public function transportBalance(): float
    {
        return max(0, $this->categoryTotalRequested(RequisitionPayment::CATEGORY_TRANSPORT) - (float) $this->transport_paid_amount);
    }

    public function airtimeBalance(): float
    {
        return max(0, $this->categoryTotalRequested(RequisitionPayment::CATEGORY_AIRTIME) - (float) $this->airtime_paid_amount);
    }

    public function totalRequestedForDay(): float
    {
        return $this->categoryTotalRequested(RequisitionPayment::CATEGORY_TRANSPORT)
            + $this->categoryTotalRequested(RequisitionPayment::CATEGORY_AIRTIME);
    }

    public function totalPaidForDay(): float
    {
        return (float) $this->transport_paid_amount + (float) $this->airtime_paid_amount;
    }

    public function totalBalanceForDay(): float
    {
        return $this->transportBalance() + $this->airtimeBalance();
    }

    public function recipientCount(): int
    {
        return max(1, count($this->recipientPhoneNumbers()));
    }

    public function categoryAmountPerRecipient(string $category): float
    {
        return (float) $this->{$category.'_amount_requested'};
    }

    public function categoryTotalRequested(string $category): float
    {
        return $this->categoryAmountPerRecipient($category) * $this->recipientCount();
    }

    /**
     * Return each recipient's remaining amount. Completed payment records are
     * authoritative. Any older aggregate paid amount without matching records
     * is allocated in recipient order so legacy payments are never sent twice.
     *
     * @return array<string, float>
     */
    public function outstandingRecipientBalances(string $category): array
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();
        $amountMinor = (int) round($this->categoryAmountPerRecipient($category) * 100);
        $completedByPhone = $payments
            ->where('category', $category)
            ->where('status', RequisitionPayment::STATUS_COMPLETED)
            ->groupBy('phone_number')
            ->map(fn ($phonePayments) => (int) $phonePayments->sum('amount_minor'));
        $recordedCompletedMinor = (int) $completedByPhone->sum();
        $aggregatePaidMinor = (int) round((float) $this->{$category.'_paid_amount'} * 100);
        $legacyCreditMinor = max(0, $aggregatePaidMinor - $recordedCompletedMinor);
        $balances = [];

        foreach ($this->recipientPhoneNumbers() as $phoneNumber) {
            $paidMinor = min($amountMinor, (int) ($completedByPhone[$phoneNumber] ?? 0));
            $legacyAppliedMinor = min($legacyCreditMinor, max(0, $amountMinor - $paidMinor));
            $legacyCreditMinor -= $legacyAppliedMinor;
            $balances[$phoneNumber] = max(0, $amountMinor - $paidMinor - $legacyAppliedMinor) / 100;
        }

        return $balances;
    }

    /** @return array<string, float> */
    public function payableRecipientBalances(string $category): array
    {
        return collect($this->outstandingRecipientBalances($category))
            ->filter(fn (float $balance, string $phoneNumber) => $balance > 0 && ! $this->activePayment($category, $phoneNumber))
            ->all();
    }

    /** @return Collection<string, RequisitionPayment> */
    public function latestPaymentsByRecipient(string $category): Collection
    {
        return $this->payments
            ->where('category', $category)
            ->sortByDesc('id')
            ->unique('phone_number')
            ->keyBy('phone_number');
    }
}
