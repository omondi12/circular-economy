<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A Local Service Order - a tender/service opportunity awarded to a
 * person/company, recorded by the RM who covers it. This is unrelated to
 * the existing Collection model, which the field team also informally
 * calls an "LSO" (see DashboardController's doc comment) - that's legacy
 * jargon for the waste/material submission workflow, a different
 * business concept entirely.
 *
 * original_amount_minor is the value stated on the physical LSO document
 * and must never be treated as money collected. Only confirmed
 * LsoPayment rows represent real collected money - see confirmedCollectedMinor().
 */
class Lso extends Model
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FULLY_PAID = 'fully_paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference_number',
        'user_id',
        'customer_name',
        'customer_contact',
        'description',
        'ministry_id',
        'state_department_id',
        'institution_id',
        'state_corporation_id',
        'original_amount_minor',
        'issue_date',
        'status',
        'document_path',
        'document_original_filename',
        'document_mime',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'original_amount_minor' => 'integer',
            'issue_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(GovernmentEntity::class, 'ministry_id');
    }

    public function stateDepartmentEntity(): BelongsTo
    {
        return $this->belongsTo(GovernmentEntity::class, 'state_department_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(GovernmentEntity::class, 'institution_id');
    }

    public function stateCorporation(): BelongsTo
    {
        return $this->belongsTo(StateCorporation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LsoPayment::class);
    }

    /**
     * Every lot recorded under this LSO - reached through Collection
     * (which is what collections.lso_id actually links), never a direct
     * lso_id on LsoLot itself. See the lso_lots migration's docblock.
     */
    public function lots(): HasManyThrough
    {
        return $this->hasManyThrough(LsoLot::class, Collection::class, 'lso_id', 'collection_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Restricts to LSOs a viewer may see - own only for an RM, own team
     * (plus themselves) for a supervisor, everything for admin/office
     * admin/operations. Mirrors AuditLog::scopeVisibleTo() /
     * StateCorporation::scopeVisibleTo().
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if (in_array($viewer->role, [User::ROLE_ADMIN, User::ROLE_OFFICE_ADMIN, User::ROLE_OPERATIONS], true)) {
            return $query;
        }

        if ($viewer->isSupervisor()) {
            $rmIds = $viewer->rms()->pluck('id')->push($viewer->id);

            return $query->whereIn('user_id', $rmIds);
        }

        return $query->where('user_id', $viewer->id);
    }

    /**
     * Only confirmed payments count as real money collected - a recorded
     * (self-reported, unconfirmed), rejected, or cancelled payment must
     * never inflate this. Uses the loaded `payments` relation when
     * available so list views can eager-load once rather than querying
     * per row.
     */
    public function confirmedCollectedMinor(): int
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return (int) $payments->where('status', LsoPayment::STATUS_CONFIRMED)->sum('amount_minor');
    }

    public function outstandingMinor(): int
    {
        return max(0, $this->original_amount_minor - $this->confirmedCollectedMinor());
    }

    public function isFullyPaid(): bool
    {
        return $this->confirmedCollectedMinor() >= $this->original_amount_minor;
    }

    /**
     * DEFERRED — EXTERNAL PRICING DOCUMENT PENDING: null until every lot
     * has a priced expected_revenue_minor (see LsoLot's docblock).
     * Deliberately all-or-nothing rather than a partial sum - a total
     * that silently excludes unpriced lots would misrepresent itself as
     * the whole LSO's expected revenue.
     */
    public function expectedRevenueMinor(): ?int
    {
        $lots = $this->relationLoaded('lots') ? $this->lots : $this->lots()->get();

        if ($lots->isEmpty() || $lots->contains(fn (LsoLot $lot) => $lot->expected_revenue_minor === null)) {
            return null;
        }

        return (int) $lots->sum('expected_revenue_minor');
    }
}
