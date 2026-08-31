<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "State Corporations" (what the boss calls Level 2's parastatal
 * category) - a flat, standalone registry seeded from the official
 * "LIST OF STATE CORPORATIONS (348 No.) AS AT 5.2.2024" (ANNEX I), not a
 * nested child of GovernmentEntity - the counts don't reconcile (348 here
 * vs ~137 institutions already seeded under ministries/departments in the
 * old hierarchy import) and this list carries its own attributes (cluster/
 * class/subclass, phase) that GovernmentEntity has no room for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_corporations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('cluster')->nullable();
            $table->string('class')->nullable();
            $table->string('subclass')->nullable();
            $table->unsignedTinyInteger('phase');
            $table->foreignId('assigned_rm_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_corporations');
    }
};
