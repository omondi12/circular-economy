<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('state_corporations', function (Blueprint $table) {
            $table->string('ceo_name')->nullable()->after('assigned_rm_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('state_corporations', function (Blueprint $table) {
            $table->dropColumn('ceo_name');
        });
    }
};
