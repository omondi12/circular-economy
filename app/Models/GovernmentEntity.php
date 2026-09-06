<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentEntity extends Model
{
    public const LEVEL_MINISTRY = 1;

    public const LEVEL_STATE_DEPARTMENT = 2;

    public const LEVEL_INSTITUTION = 3;

    protected $fillable = ['parent_id', 'name', 'type', 'level', 'status', 'assigned_rm_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'ministry_id');
    }

    public function assignedRm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_rm_id');
    }

    public function scopeMinistries($query)
    {
        return $query->where('level', self::LEVEL_MINISTRY);
    }

    /**
     * Same supervisor scoping as StateCorporation::scopeVisibleTo - a
     * supervisor manages only ministries assigned to their own RMs, or
     * still unassigned. Admins are unrestricted.
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if (! $viewer->isSupervisor()) {
            return $query;
        }

        $rmIds = $viewer->rms()->pluck('id');

        return $query->where(function ($q) use ($rmIds) {
            $q->whereIn('assigned_rm_id', $rmIds)->orWhereNull('assigned_rm_id');
        });
    }
}
