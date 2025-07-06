<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_activity_id',
        'user_id',
        'status',
        'role',
        'invited_by',
        'invited_at',
        'responded_at',
        'invitation_message',
        'joined_at',
        'left_at',
        'time_spent_minutes',
        'participation_data',
        'score',
        'notes',
        'activity_rating',
        'feedback',
    ];

    protected $casts = [
        'time_spent_minutes' => 'integer',
        'score' => 'integer',
        'activity_rating' => 'integer',
        'participation_data' => 'array',
        'invited_at' => 'datetime',
        'responded_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(ResourceActivity::class, 'resource_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Scopes
     */
    public function scopeInvited($query)
    {
        return $query->where('status', 'invited');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeParticipating($query)
    {
        return $query->where('status', 'participating');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Méthodes utilitaires
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $this->activity->updateParticipantCount();
    }

    public function decline(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    public function join(): void
    {
        if ($this->status === 'accepted') {
            $this->update([
                'status' => 'participating',
                'joined_at' => now(),
            ]);
        }
    }

    public function leave(): void
    {
        $this->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        $this->activity->updateParticipantCount();
    }

    public function complete(int $score = null, array $data = null): void
    {
        $updates = [
            'status' => 'completed',
            'left_at' => now(),
        ];

        if ($score !== null) {
            $updates['score'] = $score;
        }

        if ($data !== null) {
            $updates['participation_data'] = array_merge($this->participation_data ?? [], $data);
        }

        $this->update($updates);
    }

    public function addTimeSpent(int $minutes): void
    {
        $this->increment('time_spent_minutes', $minutes);
    }

    public function rate(int $rating, string $feedback = null): void
    {
        $this->update([
            'activity_rating' => max(1, min(5, $rating)),
            'feedback' => $feedback,
        ]);
    }

    public function updateParticipationData(array $data): void
    {
        $currentData = $this->participation_data ?? [];
        $this->update([
            'participation_data' => array_merge($currentData, $data)
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['accepted', 'participating']);
    }

    public function canParticipate(): bool
    {
        return $this->status === 'accepted' && $this->activity->status === 'in_progress';
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

        static::updated(function ($participant) {
            if ($participant->isDirty('status')) {
                $participant->activity->updateParticipantCount();
            }
        });

        static::deleted(function ($participant) {
            $participant->activity->updateParticipantCount();
        });
    }
}
