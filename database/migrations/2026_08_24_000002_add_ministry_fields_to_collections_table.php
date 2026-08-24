<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable/additive, same reasoning as the earlier Lot migration - an RM
 * recording a collection from a county or commission (not a national
 * ministry) has nothing to select here, so entity_name stays the source of
 * truth for display; these three just add structured drill-down when a
 * ministry path was actually chosen in the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('ministry_id')->nullable()->after('user_id')->constrained('government_entities')->nullOnDelete();
            $table->foreignId('state_department_id')->nullable()->after('ministry_id')->constrained('government_entities')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->after('state_department_id')->constrained('government_entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropConstrainedForeignId('state_department_id');
            $table->dropConstrainedForeignId('ministry_id');
        });
    }
};
