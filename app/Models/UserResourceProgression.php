<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResourceProgression extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resource_id',
        'status',
        'progress_percentage',
        'progress_data',
        'user_notes',
        'user_rating',
        'user_review',
        'time_spent_minutes',
        'started_at',
        'completed_at',
        'last_accessed_at',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'progress_data' => 'array',
        'user_rating' => 'integer',
        'time_spent_minutes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
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

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeBookmarked($query)
    {
        return $query->where('status', 'bookmarked');
    }

    public function scopeNotStarted($query)
    {
        return $query->where('status', 'not_started');
    }

    public function scopeRecentlyAccessed($query, $days = 7)
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    /**
     * Méthodes utilitaires
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isBookmarked(): bool
    {
        return $this->status === 'bookmarked';
    }

    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => $this->started_at ?? now(),
            'last_accessed_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ]);
    }

    public function bookmark(): void
    {
        $this->update([
            'status' => 'bookmarked',
            'last_accessed_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update([
            'status' => 'paused',
            'last_accessed_at' => now(),
        ]);
    }

    public function updateProgress(int $percentage, array $data = null): void
    {
        $updates = [
            'progress_percentage' => min(100, max(0, $percentage)),
            'last_accessed_at' => now(),
        ];

        if ($data) {
            $updates['progress_data'] = array_merge($this->progress_data ?? [], $data);
        }

        if ($percentage >= 100) {
            $updates['status'] = 'completed';
            $updates['completed_at'] = now();
        } elseif ($percentage > 0 && $this->status === 'not_started') {
            $updates['status'] = 'in_progress';
            $updates['started_at'] = $this->started_at ?? now();
        }

        $this->update($updates);
    }

    public function addTimeSpent(int $minutes): void
    {
        $this->increment('time_spent_minutes', $minutes);
        $this->update(['last_accessed_at' => now()]);
    }

    public function rate(int $rating, string $review = null): void
    {
        $this->update([
            'user_rating' => max(1, min(5, $rating)),
            'user_review' => $review,
        ]);
    }

    public function getFormattedTimeSpentAttribute(): string
    {
        $minutes = $this->time_spent_minutes;

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remainingMinutes}min";
    }

    /**
     * Boot method pour gérer les événements
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($progression) {
            // Mettre à jour les statistiques de la ressource si note donnée
            if ($progression->isDirty('user_rating') && $progression->user_rating) {
                $progression->resource->updateAverageRating();
            }
        });
    }
}
