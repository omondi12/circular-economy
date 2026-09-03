<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'Constitutional Commission', 'Independent Office', 'Judiciary', 'Legislature',
    ];

    protected $fillable = ['name', 'cluster', 'class', 'subclass', 'classification', 'ministry_id', 'phase', 'assigned_rm_id'];

    protected function casts(): array
    {
        return ['phase' => 'integer'];
    }

    public function assignedRm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_rm_id');
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
        if ($this->ministry) {
            return $this->ministry->name;
        }

        if (in_array($this->classification, self::NO_MINISTRY_CLASSIFICATIONS, true)) {
            return 'Independent';
        }

        return '—';
    }

    public function scopePhaseOne($query)
    {
        return $query->where('phase', self::PHASE_ONE);
    }

    public function scopePhaseTwo($query)
    {
        return $query->where('phase', self::PHASE_TWO);
    }
}
