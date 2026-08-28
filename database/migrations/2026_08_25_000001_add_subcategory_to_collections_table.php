<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1 now classifies Category -> Subcategory (Lot 2 stays Category only,
 * per the brief), so subcategory is nullable and only ever populated for
 * Lot 1 rows. `description` is the brief's optional free-text field (e.g.
 * "Assorted used office chairs") - distinct from the legacy
 * `other_material_name` column, which only ever applied to the old
 * Paper/Metal/... material form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('subcategory')->nullable()->after('category');
            $table->string('description')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'description']);
        });
    }
};
