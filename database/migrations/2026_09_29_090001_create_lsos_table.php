<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per Local Service Order (a tender/service opportunity
     * awarded to a person/company) recorded by the RM who covers it.
     * Independent of the existing `collections` table - Collection is
     * the pre-existing waste/material submission workflow; the field
     * team's informal use of "LSO" for a Collection entry is unrelated
     * legacy jargon and has no bearing on this table.
     *
     * original_amount_minor is the value stated on the physical LSO
     * document - never money actually collected. Real collected money
     * lives in lso_payments, so an LSO can be linked to zero, one, or
     * many payment events without ever mutating this row's amount.
     */
    public function up(): void
    {
        Schema::create('lsos', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_contact', 50)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('ministry_id')->nullable()->constrained('government_entities')->nullOnDelete();
            $table->foreignId('state_department_id')->nullable()->constrained('government_entities')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('government_entities')->nullOnDelete();
            $table->foreignId('state_corporation_id')->nullable()->constrained('state_corporations')->nullOnDelete();
            $table->unsignedBigInteger('original_amount_minor');
            $table->date('issue_date');
            $table->string('status', 20)->default('recorded');
            $table->string('document_path')->nullable();
            $table->string('document_original_filename')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsos');
    }
};
