<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NawiriTreasuryCredential extends Model
{
    public const SINGLETON_KEY = 'primary';

    protected $attributes = [
        'singleton_key' => self::SINGLETON_KEY,
    ];

    protected $fillable = [
        'email',
        'password',
        'pin',
        'verified_at',
        'updated_by_id',
    ];

    protected $hidden = [
        'password',
        'pin',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'pin' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public static function current(): ?self
    {
        return static::query()->where('singleton_key', self::SINGLETON_KEY)->first();
    }
}
