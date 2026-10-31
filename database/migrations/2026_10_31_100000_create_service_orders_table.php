<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 2 (Disposal) service orders: the client's own order for the disposal work (a hospital's "Local Service Order"),
 * with its scanned document, attached to the collections it covers. Lot 2 has no Lso record (that is Lot 1's sale
 * order), so this is where its paperwork lives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 100);
            $table->string('customer_name');
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // the RM
            $table->date('order_date')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable(); // the total stated on the order
            $table->string('document_path');
            $table->string('document_original_filename');
            $table->string('document_mime', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'reference_number']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('service_order_id')->nullable()->after('lso_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_order_id');
        });
        Schema::dropIfExists('service_orders');
    }
};
