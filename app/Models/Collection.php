<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    public const MATERIALS = [
        'paper_kg' => 'Paper',
        'metal_kg' => 'Metal',
        'plastic_kg' => 'Plastic',
        'furniture_kg' => 'Furniture',
        'ewaste_kg' => 'E-Waste',
        'other_kg' => 'Other',
    ];

    protected $fillable = [
        'entity_name',
        'relationship_manager',
        'state_department',
        'department_agency',
        'location_office',
        'contact_person_name',
        'contact_person_number',
        'paper_kg',
        'metal_kg',
        'plastic_kg',
        'furniture_kg',
        'ewaste_kg',
        'other_material_name',
        'other_kg',
        'collection_date',
        'collected_by',
    ];

    protected $casts = [
        'paper_kg' => 'float',
        'metal_kg' => 'float',
        'plastic_kg' => 'float',
        'furniture_kg' => 'float',
        'ewaste_kg' => 'float',
        'other_kg' => 'float',
        'collection_date' => 'date',
    ];

    public function totalKg(): float
    {
        return $this->paper_kg + $this->metal_kg + $this->plastic_kg
            + $this->furniture_kg + $this->ewaste_kg + $this->other_kg;
    }
}
