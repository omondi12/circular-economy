<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot 1 (Auction) commission, per Tender No. TNT/KEPDA/011/2026-2027:
     * 10% of the first KES 100,000 of realized sale value, 7% above it -
     * see App\Services\LotPricingService. `amount_minor` keeps its
     * existing meaning untouched everywhere (the money that actually
     * counts toward outstanding balances/targets/reporting - Westport's
     * own collectible revenue). This column additively preserves the
     * underlying gross sale value a Lot-1 payment's commission was
     * computed from, purely for transparency/audit - never used in any
     * existing calculation, so no other code path changes meaning.
     * Nullable and unused for payments that don't go through the gross-
     * value commission calculator (including every payment recorded
     * before this migration).
     */
    public function up(): void
    {
        Schema::table('lso_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('gross_amount_minor')->nullable()->after('amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('lso_payments', function (Blueprint $table) {
            $table->dropColumn('gross_amount_minor');
        });
    }
};
