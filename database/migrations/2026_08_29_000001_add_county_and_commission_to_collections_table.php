<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entity Type is now 3-way (National Government Ministry / County /
 * Commission / Other), replacing the old 2-way ministry/free-text-other
 * split. `department_agency` already exists and is reused here - it's a
 * free-text input for the ministry path, but a controlled dropdown value
 * (validated against EntityDirectory) for the county/commission paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('county')->nullable()->after('entity_name');
            $table->string('commission')->nullable()->after('county');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['county', 'commission']);
        });
    }
};
