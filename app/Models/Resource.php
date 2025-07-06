<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'content',
        'slug',
        'category_id',
        'resource_type_id',
        'created_by',
        'validated_by',
        'visibility',
        'status',
        'file_path',
        'file_name',
        'file_mime_type',
        'file_size',
        'external_url',
        'duration_minutes',
        'difficulty_level',
        'tags',
        'view_count',
        'download_count',
        'favorite_count',
        'average_rating',
        'rating_count',
        'published_at',
        'validated_at',
        'last_viewed_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'view_count' => 'integer',
        'download_count' => 'integer',
        'favorite_count' => 'integer',
        'rating_count' => 'integer',
        'duration_minutes' => 'integer',
        'file_size' => 'integer',
        'average_rating' => 'decimal:2',
        'published_at' => 'datetime',
        'validated_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function relationTypes(): BelongsToMany
    {
        return $this->belongsToMany(RelationType::class, 'resource_relation_types');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->approved();
    }

    public function favoriteUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_resource_favorites');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserResourceFavorite::class);
    }

    public function progressions(): HasMany
    {
        return $this->hasMany(UserResourceProgression::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ResourceActivity::class);
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('resource_type_id', $typeId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->whereFullText(['title', 'description'], $search)
            ->orWhere('title', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }

    /**
     * Mutators & Accessors
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    public function getFormattedFileSizeAttribute()
    {
        if (!$this->file_size) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Méthodes utilitaires
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function canBeAccessedBy(?User $user = null): bool
    {
        if ($this->visibility === 'public' && $this->isPublished()) {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        if ($this->visibility === 'shared' && $this->isPublished()) {
            return true;
        }

        return false;
    }

    public function isFavoritedBy(?User $user = null): bool
    {
        if (!$user) return false;
        return $this->favorites()->where('user_id', $user->id)->exists();
    }

    public function updateAverageRating(): void
    {
        $ratings = $this->progressions()->whereNotNull('user_rating')->get();

        if ($ratings->count() > 0) {
            $average = $ratings->avg('user_rating');
            $this->update([
                'average_rating' => round($average, 2),
                'rating_count' => $ratings->count(),
            ]);
        }
    }
}
