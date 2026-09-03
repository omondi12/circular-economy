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
}
