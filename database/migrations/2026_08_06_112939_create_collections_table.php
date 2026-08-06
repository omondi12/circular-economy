<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();

            $table->string('entity_name');
            $table->string('relationship_manager')->nullable();
            $table->string('state_department')->nullable();
            $table->string('department_agency')->nullable();
            $table->string('location_office')->nullable();
            $table->string('contact_person_name');
            $table->string('contact_person_number');

            $table->decimal('paper_kg', 10, 2)->default(0);
            $table->decimal('metal_kg', 10, 2)->default(0);
            $table->decimal('plastic_kg', 10, 2)->default(0);
            $table->decimal('furniture_kg', 10, 2)->default(0);
            $table->decimal('ewaste_kg', 10, 2)->default(0);
            $table->string('other_material_name')->nullable();
            $table->decimal('other_kg', 10, 2)->default(0);

            $table->date('collection_date');
            $table->string('collected_by')->nullable();

            $table->timestamps();

            $table->index('entity_name');
            $table->index('collection_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
