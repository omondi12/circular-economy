<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client's order for Lot 2 disposal work (e.g. a hospital's Local Service Order) with its scanned document,
 * attached to the collections it covers.
 */
class ServiceOrder extends Model
{
    protected $fillable = [
        'reference_number',
        'customer_name',
        'user_id',
        'order_date',
        'amount_minor',
        'document_path',
        'document_original_filename',
        'document_mime',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'amount_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * What the attached collections are expected to earn at the contract rates, to check against the order's total.
     */
    public function expectedRevenueMinor(): int
    {
        return (int) $this->collections->sum(fn (Collection $c) => (int) $c->lsoLot?->expected_revenue_minor);
    }
}
