<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not wired into the RM submission form yet (that's the boss's Level 4 -
 * Account Managers running an availability survey against Level 3
 * materials, a separate future submission flow) - added now so the
 * Feasibility Study "by state corporation" view has a real column to
 * group on once that flow exists, rather than a dead end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('state_corporation_id')->nullable()->after('institution_id')->constrained('state_corporations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_corporation_id');
        });
    }
};
