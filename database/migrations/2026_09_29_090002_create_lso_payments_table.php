<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per actual money-collection event against an LSO - the
     * source of truth for real collected money (see the Lso model's
     * docblock). An LSO can have zero, one, or many of these; only rows
     * with status=confirmed ever count toward outstanding balances, RM
     * target achievement, or future commission - a recorded (self-
     * reported, unconfirmed), rejected, or cancelled row must not.
     */
    public function up(): void
    {
        Schema::create('lso_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lso_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->date('collected_at');
            $table->string('status', 20)->default('recorded');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lso_id', 'status']);
            $table->index('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lso_payments');
    }
};
