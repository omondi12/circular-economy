<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One County Government client in a supervisor's area (RmAllocation).
 */
class SupervisorCounty extends Model
{
    protected $fillable = ['supervisor_id', 'state_corporation_id'];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(StateCorporation::class, 'state_corporation_id');
    }
}
