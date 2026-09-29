<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a Collection to the Lso record created alongside it when the
     * RM records a Lot 1 (Sale) collection with LSO details - the LSO's
     * reference number, value and document live on the Lso row itself
     * (single source of truth for Finance/Admin), this column just ties
     * the two together. Null for Lot 2 (Disposal) collections, which
     * have no monetary value and so never get an Lso.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('lso_id')->nullable()->after('state_corporation_id')->constrained('lsos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lso_id');
        });
    }
};
