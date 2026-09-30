<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pricing overlay for one lot/collection line item under a financial
 * LSO - one LsoLot per Collection (see the collections migration's
 * unique collection_id). Reuses Collection's existing lot/category/
 * subcategory/quantity/unit rather than duplicating them; see this
 * migration's docblock for why there's no lso_id column here.
 *
 * rate_minor and expected_revenue_minor are DEFERRED — EXTERNAL PRICING
 * DOCUMENT PENDING: the company's category/method (Auction vs Disposal)
 * pricing lives outside this system and hasn't been provided yet. They
 * stay genuinely null (never 0) until that pricing engine is built, so
 * "not yet priced" is never confused with "priced at zero".
 */
class LsoLot extends Model
{
    protected $fillable = [
        'collection_id',
        'rate_minor',
        'expected_revenue_minor',
    ];

    protected function casts(): array
    {
        return [
            'rate_minor' => 'integer',
            'expected_revenue_minor' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
