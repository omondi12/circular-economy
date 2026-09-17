<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * One row per requester (RM or Supervisor) per working day - the daily
     * transport + airtime facilitation request, per the boss's brief
     * (2026-09-17). Transport and airtime are tracked independently since
     * each can be approved/paid separately (e.g. airtime approved same
     * day, transport pending) - the boss's own column list gives each its
     * own "Approved By", "Paid Amount" and requested-at timestamp.
     */
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('institution_visiting');
            $table->date('working_day');

            $table->timestamp('transport_requested_at');
            $table->decimal('transport_amount_requested', 10, 2)->default(1500);
            $table->string('transport_status')->default('pending');
            $table->foreignId('transport_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transport_approved_at')->nullable();
            $table->decimal('transport_paid_amount', 10, 2)->default(0);

            $table->timestamp('airtime_requested_at');
            $table->decimal('airtime_amount_requested', 10, 2)->default(250);
            $table->string('airtime_status')->default('pending');
            $table->foreignId('airtime_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('airtime_approved_at')->nullable();
            $table->decimal('airtime_paid_amount', 10, 2)->default(0);

            $table->timestamps();

            $table->index(['requester_id', 'working_day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
