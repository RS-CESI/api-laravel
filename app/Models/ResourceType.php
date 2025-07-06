<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'is_active',
        'requires_file',
        'allowed_file_types',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_file' => 'boolean',
        'allowed_file_types' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Relation avec les ressources
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
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

    /**
     * Vérifier si un type de fichier est autorisé
     */
    public function isFileTypeAllowed(string $fileType): bool
    {
        if (!$this->requires_file || !$this->allowed_file_types) {
            return false;
        }

        return in_array(strtolower($fileType), array_map('strtolower', $this->allowed_file_types));
    }
}
