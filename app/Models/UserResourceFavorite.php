<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResourceFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resource_id',
    ];

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForResource($query, $resourceId)
    {
        return $query->where('resource_id', $resourceId);
    }

    /**
     * Boot method pour gérer les événements
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($favorite) {
            // Incrémenter le compteur de favoris de la ressource
            $favorite->resource->increment('favorite_count');
        });

        static::deleted(function ($favorite) {
            // Décrémenter le compteur de favoris de la ressource
            $favorite->resource->decrement('favorite_count');
        });
    }
}
