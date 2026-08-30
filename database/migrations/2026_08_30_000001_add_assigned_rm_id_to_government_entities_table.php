<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each ministry (level 1) is assigned to one RM, so the boss can see
 * per-RM performance across the ministries they own. Nullable/on a single
 * column rather than a pivot table since the assignment is 1 ministry ->
 * 1 RM, never many-to-many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_entities', function (Blueprint $table) {
            $table->foreignId('assigned_rm_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('government_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_rm_id');
        });
    }
};
