<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StateCorporation extends Model
{
    public const PHASE_ONE = 1;

    public const PHASE_TWO = 2;

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

    public function scopePhaseOne($query)
    {
        return $query->where('phase', self::PHASE_ONE);
    }

    public function scopePhaseTwo($query)
    {
        return $query->where('phase', self::PHASE_TWO);
    }
}
