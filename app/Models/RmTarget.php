<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An RM's collection target for an explicit period - monetary (KES, via
 * target_amount_minor) or count (number of fully-paid LSOs, via
 * target_count), admin's choice per RM per period. Not tied to a
 * calendar month by design (period_start/period_end), so quarterly or
 * custom periods work without a schema change.
 *
 * Treated as immutable once qualifying activity exists in its period
 * (enforced in RmTargetController) - correcting a target creates a new
 * row rather than mutating history, so past achievement figures stay
 * explainable.
 */
class RmTarget extends Model
{
    public const TYPE_MONETARY = 'monetary';

    public const TYPE_COUNT = 'count';

    protected $fillable = [
        'user_id',
        'type',
        'target_amount_minor',
        'target_count',
        'period_start',
        'period_end',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_amount_minor' => 'integer',
            'target_count' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Actual qualifying monetary performance for this target's period -
     * confirmed LsoPayment amounts only, keyed by when the money was
     * actually collected (collected_at), never an LSO's stated value.
     */
    public function actualAmountMinor(): int
    {
        return (int) LsoPayment::query()
            ->where('status', LsoPayment::STATUS_CONFIRMED)
            ->whereHas('lso', fn ($q) => $q->where('user_id', $this->user_id))
            ->whereBetween('collected_at', [$this->period_start, $this->period_end])
            ->sum('amount_minor');
    }

    /**
     * Actual qualifying LSO count for this target's period - an LSO
     * counts only once it becomes fully paid (confirmed payments reach
     * its original amount), attributed to the period containing the
     * confirmed payment that tipped it over. Walking the ledger in order
     * (rather than trusting the LSO's current `status`) means a later
     * payment or a cancellation can never retroactively move which
     * period an already-tipped-over LSO counted in.
     */
    public function actualCount(): int
    {
        return Lso::query()
            ->where('user_id', $this->user_id)
            ->get(['id', 'original_amount_minor'])
            ->filter(function (Lso $lso) {
                $confirmedInOrder = LsoPayment::query()
                    ->where('lso_id', $lso->id)
                    ->where('status', LsoPayment::STATUS_CONFIRMED)
                    ->orderBy('collected_at')
                    ->orderBy('id')
                    ->get(['amount_minor', 'collected_at']);

                $running = 0;
                foreach ($confirmedInOrder as $payment) {
                    $running += $payment->amount_minor;
                    if ($running >= $lso->original_amount_minor) {
                        return $payment->collected_at->between($this->period_start, $this->period_end);
                    }
                }

                return false;
            })
            ->count();
    }

    public function actual(): int
    {
        return $this->type === self::TYPE_MONETARY ? $this->actualAmountMinor() : $this->actualCount();
    }

    public function targetValue(): int
    {
        return $this->type === self::TYPE_MONETARY ? (int) $this->target_amount_minor : (int) $this->target_count;
    }

    public function achievementPercent(): float
    {
        $target = $this->targetValue();

        return $target > 0 ? round(($this->actual() / $target) * 100, 1) : 0.0;
    }
}
