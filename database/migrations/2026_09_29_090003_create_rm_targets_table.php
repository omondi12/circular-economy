<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An RM's collection target for an explicit period - monetary (KES,
     * via target_amount_minor) or count (number of fully-paid LSOs, via
     * target_count), admin's choice per RM per period. period_start/
     * period_end are explicit dates rather than a month/year pair, so
     * quarterly or custom periods work without a schema change.
     */
    public function up(): void
    {
        Schema::create('rm_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('target_amount_minor')->nullable();
            $table->unsignedInteger('target_count')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rm_targets');
    }
};
