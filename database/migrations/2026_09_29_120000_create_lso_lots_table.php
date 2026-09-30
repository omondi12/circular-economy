<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pricing overlay for one lot/collection line item under a
     * financial LSO. Deliberately does NOT duplicate lso_id, lot,
     * category, subcategory, quantity or unit - those already live on
     * `collections`, reached one-to-one via collection_id, and
     * `collections.lso_id` is already the single link from a Collection to
     * its parent Lso. Reaching an Lso's lots is therefore
     * Lso::lots() -> hasManyThrough(LsoLot::class, Collection::class),
     * which means collections.lso_id and an LsoLot's parent Lso can never
     * disagree - there is only one place the link is ever stored.
     *
     * rate_minor/expected_revenue_minor stay null until the external
     * category/method pricing document is available - see App\Models\LsoLot.
     */
    public function up(): void
    {
        Schema::create('lso_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rate_minor')->nullable();
            $table->unsignedBigInteger('expected_revenue_minor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lso_lots');
    }
};
