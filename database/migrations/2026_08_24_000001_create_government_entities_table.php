<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ministry -> State Department -> Institution, per the Kenya National
 * Government Master Hierarchy brief. Self-referencing rather than 3
 * separate tables since the brief's own "Implementation Note for IT Team"
 * describes exactly this shape (RECORD ID / PARENT ID / LEVEL). `status`
 * exists because the brief's Data Quality Rule requires every record to
 * carry one of: verified, partially_verified, pending_verification,
 * historical, moved, merged, dissolved - ministries get reorganised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('government_entities')->nullOnDelete();
            $table->string('name');
            $table->string('type'); // ministry | state_department | institution
            $table->unsignedTinyInteger('level'); // 1 | 2 | 3
            $table->string('status')->default('pending_verification');
            $table->timestamps();

            $table->index(['parent_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_entities');
    }
};
