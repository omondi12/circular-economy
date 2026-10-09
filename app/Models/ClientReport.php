<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReport extends Model
{
    protected $fillable = [
        'state_corporation_id',
        'rm_id',
        'created_by',
        'report_date',
        'engagement_type',
        'contact_person',
        'contact_person_phone',
        'outcome',
        'current_stage',
        'next_action',
        'follow_up_date',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'follow_up_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(StateCorporation::class, 'state_corporation_id');
    }

    public function rm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rm_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether an RM can still self-edit this report - 24 hours from when
     * they logged it, not from report_date (a business-meaningful date
     * they can backdate), so the window can't be extended by picking an
     * old report_date (2026-10-09, per the boss). Admin/supervisor edit
     * access is unaffected by this - see ClientReportController's
     * authorizeReportEdit() vs authorizeRmReportEdit().
     */
    public function isWithinRmEditWindow(): bool
    {
        return $this->created_at->copy()->addHours(24)->isFuture();
    }
}
