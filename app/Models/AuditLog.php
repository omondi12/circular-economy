<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * $subject is any Eloquent model the action applies to (a Collection,
     * a User) - stored polymorphically so one table covers every audited
     * action rather than a separate log table per entity.
     */
    public static function record(string $action, ?Model $subject = null, array $meta = []): self
    {
        return self::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
        ]);
    }

    /**
     * Restricts to entries "related to" a supervisor (2026-09-07): actions
     * they or their own RMs performed, or actions taken on their own RMs'
     * accounts or on a client/ministry currently assigned to one of their
     * RMs. Admins are unrestricted. Without this, a supervisor's Audit Log
     * and dashboard "Recent Activity" showed every action system-wide,
     * including other supervisors' teams.
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if (! $viewer->isSupervisor()) {
            return $query;
        }

        $rmIds = $viewer->rms()->pluck('id')->all();
        $ownAndRmIds = [...$rmIds, $viewer->id];

        $clientIds = StateCorporation::whereIn('assigned_rm_id', $rmIds)->pluck('id');
        $ministryIds = GovernmentEntity::whereIn('assigned_rm_id', $rmIds)->pluck('id');

        return $query->where(function ($q) use ($ownAndRmIds, $clientIds, $ministryIds) {
            $q->whereIn('user_id', $ownAndRmIds)
                ->orWhere(fn ($q2) => $q2->where('subject_type', (new StateCorporation)->getMorphClass())->whereIn('subject_id', $clientIds))
                ->orWhere(fn ($q2) => $q2->where('subject_type', (new GovernmentEntity)->getMorphClass())->whereIn('subject_id', $ministryIds))
                ->orWhere(fn ($q2) => $q2->where('subject_type', (new User)->getMorphClass())->whereIn('subject_id', $ownAndRmIds));
        });
    }
}
