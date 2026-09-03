<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily engagement reports against one client, per the boss's WhatsApp
 * brief (2026-09-02/03) - trimmed from his 22-question Google Form draft
 * down to the fields that carry real signal, since one person transcribes
 * these from a WhatsApp group rather than each RM self-reporting. The
 * institution and its usual RM are already known from the client the
 * report is opened against, so neither needs its own field here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_corporation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rm_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('report_date');
            $table->string('engagement_type');
            $table->string('contact_person')->nullable();
            $table->text('outcome');
            $table->string('current_stage');
            $table->string('next_action')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['state_corporation_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reports');
    }
};
