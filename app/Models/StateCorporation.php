<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StateCorporation extends Model
{
    public const PHASE_ONE = 1;

    public const PHASE_TWO = 2;

    /**
     * Classifications that are constitutionally/statutorily outside the
     * ministry hierarchy entirely - a blank ministry here isn't a data
     * gap, it's correct (per the Kenya public institutions register:
     * "for independent bodies... it explicitly sits OUTSIDE any ministry
     * rather than being forced into an inaccurate mapping").
     */
    private const NO_MINISTRY_CLASSIFICATIONS = [
        'Constitutional Commission', 'Independent Office', 'Judiciary', 'Legislature', 'Private Company',
    ];

    protected $fillable = ['name', 'cluster', 'class', 'subclass', 'classification', 'ministry_id', 'phase', 'assigned_rm_id', 'ceo_name'];

    protected function casts(): array
    {
        return ['phase' => 'integer'];
    }

    /**
     * The manual override only (2026-09-28, per the boss) - set via
     * Assign RMs -> Clients, and takes precedence over the state
     * department's RM when present. For "who actually covers this
     * client right now", use effectiveAssignedRm()/effectiveAssignedRmId()
     * instead; this raw relation is for the override value itself (e.g.
     * what the Clients tab's dropdown should show as selected).
     */
    public function assignedRm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_rm_id');
    }

    /**
     * Who actually covers this client - the manual override if one is
     * set, otherwise whoever is assigned to its state department, live
     * (2026-09-28, per the boss: "everyone... should not even have
     * clients [unless] assigned a state department... when someone is
     * assigned a state department, you get all the clients under that
     * state department"). Reassigning a department's RM immediately
     * changes what this returns for every client under it - nothing is
     * copied/cached onto the client row unless a manual override exists.
     */
    public function effectiveAssignedRm(): ?User
    {
        return $this->assignedRm ?: $this->stateDepartmentEntity()?->assignedRm;
    }

    public function effectiveAssignedRmId(): ?int
    {
        return $this->assigned_rm_id ?? $this->stateDepartmentEntity()?->assigned_rm_id;
    }

    /**
     * Every client effectively covered by $rmId - a direct (manual
     * override) match, or one whose state department is assigned to
     * $rmId. The query-level counterpart to effectiveAssignedRmId(), for
     * anywhere that needs to list/count/filter rather than check one
     * client at a time.
     *
     * @param  int|array<int>  $rmIds  One RM id, or several (e.g. a
     *                                 supervisor's whole team) - matches
     *                                 a client covered by any of them.
     */
    public function scopeAssignedToRm($query, int|array $rmIds)
    {
        $rmIds = (array) $rmIds;

        return $query->where(function ($q) use ($rmIds) {
            $q->whereIn('assigned_rm_id', $rmIds)
                ->orWhereHas('ministry', function ($q2) use ($rmIds) {
                    $q2->where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT)
                        ->whereIn('assigned_rm_id', $rmIds);
                })
                ->orWhereHas('ministry', function ($q2) use ($rmIds) {
                    $q2->where('level', GovernmentEntity::LEVEL_INSTITUTION)
                        ->whereHas('parent', function ($q3) use ($rmIds) {
                            $q3->where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT)
                                ->whereIn('assigned_rm_id', $rmIds);
                        });
                });
        });
    }

    /**
     * Whether a client's ministry_id resolves to an assigned (or
     * unassigned) state department - level-checked (2026-09-29 fix), so
     * a client whose ministry_id still points at a plain Ministry (not a
     * state department) is never counted just because that Ministry
     * happens to carry an old assigned_rm_id from the retired
     * ministry-wide cascade. Shared by scopeEffectivelyAssigned() and
     * scopeEffectivelyUnassigned() below so the two can never drift out
     * of being exact opposites of each other.
     */
    public function scopeEffectivelyAssigned($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('assigned_rm_id')
                ->orWhereHas('ministry', function ($q2) {
                    $q2->where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT)
                        ->whereNotNull('assigned_rm_id');
                })
                ->orWhereHas('ministry', function ($q2) {
                    $q2->where('level', GovernmentEntity::LEVEL_INSTITUTION)
                        ->whereHas('parent', function ($q3) {
                            $q3->where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT)
                                ->whereNotNull('assigned_rm_id');
                        });
                });
        });
    }

    /**
     * The exact logical negation of scopeEffectivelyAssigned() - kept as
     * a plain "not" of that scope rather than a separately hand-written
     * condition, so the two can never silently disagree.
     */
    public function scopeEffectivelyUnassigned($query)
    {
        return $query->whereNot(fn ($q) => $q->effectivelyAssigned());
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(GovernmentEntity::class, 'ministry_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ClientReport::class);
    }

    /**
     * "Independent" for bodies that genuinely have no ministry by design,
     * the ministry name where one is set, or "—" for the small remainder
     * whose ministry is a real unresolved gap (e.g. a ministry name that
     * doesn't cleanly match after a cabinet reshuffle) - so the Clients
     * page never shows a blank cell without explaining which case it is.
     */
    public function ministryDisplay(): string
    {
        $entity = $this->ministry;

        if ($entity) {
            // $ministry can be assigned at any level since
            // clients:link-state-departments started pointing it at a
            // client's real state department (2026-09-28) rather than
            // always the top-level ministry - this always walks up to
            // the actual Ministry name for this column, regardless of
            // which level ministry_id happens to point at. Use
            // stateDepartmentDisplay() for the department itself.
            return match ($entity->level) {
                GovernmentEntity::LEVEL_STATE_DEPARTMENT => $entity->parent?->name ?? $entity->name,
                GovernmentEntity::LEVEL_INSTITUTION => $entity->parent?->parent?->name ?? $entity->parent?->name ?? $entity->name,
                default => $entity->name,
            };
        }

        if ($this->classification === 'Private Company') {
            return 'Private company';
        }

        if (in_array($this->classification, self::NO_MINISTRY_CLASSIFICATIONS, true)) {
            return 'Independent';
        }

        return '—';
    }

    /**
     * The state department this client falls under - one level down from
     * ministryDisplay() (2026-09-24, per the boss). $ministry can be
     * assigned at any level (ministry, state department, or institution),
     * so this walks up from an institution to its department, shows a
     * department directly, or "—" when the client is assigned straight to
     * a ministry (no specific department) or has no ministry at all.
     */
    public function stateDepartmentDisplay(): string
    {
        return $this->stateDepartmentEntity()?->name ?? '—';
    }

    /**
     * Who the RM should call at this client's state department - the
     * contact person set via Assign RMs -> State Departments (2026-09-28).
     * Null when the department has none on file yet, same as
     * stateDepartmentEntity() being null when the client isn't linked to
     * one at all.
     */
    public function stateDepartmentContactName(): ?string
    {
        return $this->stateDepartmentEntity()?->contact_person_name;
    }

    public function stateDepartmentContactPhone(): ?string
    {
        return $this->stateDepartmentEntity()?->contact_person_phone;
    }

    /**
     * Shared resolution behind stateDepartmentDisplay() and the contact
     * lookups above - $ministry can be assigned at any level (ministry,
     * state department, or institution), so this walks up from an
     * institution to its department, returns a department directly, or
     * null when the client is assigned straight to a ministry (no
     * specific department) or has no ministry at all.
     */
    private function stateDepartmentEntity(): ?GovernmentEntity
    {
        $entity = $this->ministry;

        if (! $entity) {
            return null;
        }

        if ($entity->level === GovernmentEntity::LEVEL_STATE_DEPARTMENT) {
            return $entity;
        }

        if ($entity->level === GovernmentEntity::LEVEL_INSTITUTION && $entity->parent?->level === GovernmentEntity::LEVEL_STATE_DEPARTMENT) {
            return $entity->parent;
        }

        return null;
    }

    public function scopePhaseOne($query)
    {
        return $query->where('phase', self::PHASE_ONE);
    }

    public function scopePhaseTwo($query)
    {
        return $query->where('phase', self::PHASE_TWO);
    }

    /**
     * Restricts to clients a supervisor can manage: assigned to one of
     * their own RMs, or still unassigned (so they can pick it up for their
     * team). Admins are unrestricted. Used only in the admin area - the
     * public Clients page stays a full, unscoped register.
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if (! $viewer->isSupervisor()) {
            return $query;
        }

        $allowedRmIds = $viewer->rms()->pluck('id')->merge(User::orphanedRms()->pluck('id'))->all();

        // Effective visibility now depends on the state department too
        // (2026-09-28) - precompute which departments/institutions
        // resolve to an allowed RM (or none yet), so a client with no
        // manual override still shows up correctly for this supervisor.
        $allowedDepartmentIds = GovernmentEntity::where('level', GovernmentEntity::LEVEL_STATE_DEPARTMENT)
            ->get(['id', 'assigned_rm_id'])
            ->filter(fn (GovernmentEntity $d) => $d->assigned_rm_id === null || in_array($d->assigned_rm_id, $allowedRmIds, true))
            ->pluck('id');

        $allowedEntityIds = $allowedDepartmentIds->merge(
            GovernmentEntity::where('level', GovernmentEntity::LEVEL_INSTITUTION)
                ->whereIn('parent_id', $allowedDepartmentIds)
                ->pluck('id')
        );

        return $query->where(function ($q) use ($allowedRmIds, $allowedEntityIds) {
            $q->whereIn('assigned_rm_id', $allowedRmIds)
                ->orWhere(function ($q2) use ($allowedEntityIds) {
                    $q2->whereNull('assigned_rm_id')
                        ->where(function ($q3) use ($allowedEntityIds) {
                            $q3->whereNull('ministry_id')->orWhereIn('ministry_id', $allowedEntityIds);
                        });
                });
        });
    }

    /**
     * The contact person from this client's most recent engagement report,
     * if any - shown on the public Clients page rather than a separate
     * manually-maintained field, since the report log already captures it.
     */
    public function latestReport(): HasOne
    {
        return $this->hasOne(ClientReport::class)->latestOfMany(['report_date', 'id']);
    }
}
