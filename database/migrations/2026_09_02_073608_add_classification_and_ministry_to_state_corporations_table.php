<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the governance-status axis the boss asked for ("IEBC is
 * Independent") plus a supervising-ministry link, so the registry can
 * carry counties (Council of Governors), National Polytechnics (Ministry
 * of Education), IEBC (a constitutional commission, no ministry) and the
 * Government Printer (a department, not a corporate body) alongside the
 * original 348 Annex-I state corporations - without overloading the
 * existing cluster/class/subclass fields, which are the SCAC's operational
 * category (EXECUTIVE AGENCIES, REGULATORY AGENCIES, ...), a different axis
 * from legal/governance status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_corporations', function (Blueprint $table) {
            $table->string('classification')->nullable()->after('subclass');
            $table->foreignId('ministry_id')->nullable()->after('classification')
                ->constrained('government_entities')->nullOnDelete();
        });

        // Every pre-existing row is a genuine Annex-I state corporation -
        // backfill them so the new column reads as "unknown" nowhere.
        DB::table('state_corporations')->whereNull('classification')->update(['classification' => 'State Corporation']);
    }

    public function down(): void
    {
        Schema::table('state_corporations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ministry_id');
            $table->dropColumn('classification');
        });
    }
};
