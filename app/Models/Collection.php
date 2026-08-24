<?php

namespace App\Models;

use App\Support\WasteCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'lot',
        'category',
        'quantity',
        'unit',
        'user_id',
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
        'lot' => 'integer',
        'quantity' => 'float',
        'paper_kg' => 'float',
        'metal_kg' => 'float',
        'plastic_kg' => 'float',
        'furniture_kg' => 'float',
        'ewaste_kg' => 'float',
        'other_kg' => 'float',
        'collection_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pre-Lot submissions (the 1 historical row) only ever had the flat
     * material columns; everything entered through the new form has lot
     * set instead. Kept so old data keeps rendering correctly.
     */
    public function isLegacyMaterialEntry(): bool
    {
        return $this->lot === null;
    }

    public function totalKg(): float
    {
        return $this->paper_kg + $this->metal_kg + $this->plastic_kg
            + $this->furniture_kg + $this->ewaste_kg + $this->other_kg;
    }

    public function lotLabel(): ?string
    {
        return WasteCategories::lotLabel($this->lot);
    }

    public function categoryLabel(): ?string
    {
        return WasteCategories::categoryLabel($this->lot, $this->category);
    }
}
