<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supervisor areas (2026-11-03, per the boss: "no supervisor should have less than 4 counties or more than 5"):
 * which County Government clients fall in each supervisor's area. A county is in one area at most.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_counties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('state_corporation_id')->unique()->constrained('state_corporations')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_counties');
    }
};
