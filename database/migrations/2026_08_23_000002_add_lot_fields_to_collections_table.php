<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive/nullable only - the live table already has 1 real submission
 * recorded under the old flat Paper/Metal/Plastic/... columns. Those columns
 * stay untouched so that row keeps rendering; every new submission going
 * forward is entered through the Lot -> Category structure instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->unsignedTinyInteger('lot')->nullable()->after('entity_name');
            $table->string('category')->nullable()->after('lot');
            $table->decimal('quantity', 12, 2)->nullable()->after('category');
            $table->string('unit', 10)->nullable()->after('quantity');
            $table->foreignId('user_id')->nullable()->after('unit')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['lot', 'category', 'quantity', 'unit']);
        });
    }
};
