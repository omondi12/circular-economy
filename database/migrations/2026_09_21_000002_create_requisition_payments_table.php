<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('phone_number', 20);
            $table->string('provider', 20);
            $table->string('status', 30)->index();
            $table->string('idempotency_key', 91)->unique();
            $table->uuid('nawiri_payment_id')->nullable()->index();
            $table->string('provider_reference', 150)->nullable();
            $table->string('provider_order_id', 150)->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['requisition_id', 'category', 'status'], 'requisition_payment_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_payments');
    }
};
