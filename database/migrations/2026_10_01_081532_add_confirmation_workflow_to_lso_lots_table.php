<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot 2's "what Westport makes" wasn't an authoritative figure until
     * now - expected_revenue_minor is computed the moment an RM records
     * the collection and was never verified by Finance (2026-10-01, per
     * the boss's RM commission clarification: RM commission requires a
     * CONFIRMED Westport-earned amount, and Lot 2 had no such thing).
     * Deliberately additive, same shape as LsoPayment's status/confirmed_by
     * columns - expected_revenue_minor keeps its exact existing meaning
     * (the self-reported/calculated estimate), confirmed_revenue_minor is
     * the new, separate, Finance-locked authoritative amount RM commission
     * is actually computed from.
     */
    public function up(): void
    {
        Schema::table('lso_lots', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('expected_revenue_minor');
            $table->unsignedBigInteger('confirmed_revenue_minor')->nullable()->after('status');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_revenue_minor')->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lso_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['status', 'confirmed_revenue_minor', 'confirmed_at']);
        });
    }
};
