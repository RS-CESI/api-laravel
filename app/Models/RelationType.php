<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RelationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relation many-to-many avec les ressources
     * Une ressource peut concerner plusieurs types de relations
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_relation_types');
    }

    /**
     * Scope pour récupérer seulement les types actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour ordonner par sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
