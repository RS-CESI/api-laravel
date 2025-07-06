<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResourceActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'resource_id',
        'created_by',
        'status',
        'max_participants',
        'is_private',
        'access_code',
        'scheduled_at',
        'started_at',
        'completed_at',
        'estimated_duration_minutes',
        'activity_data',
        'results',
        'participant_count',
        'instructions',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'max_participants' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'participant_count' => 'integer',
        'activity_data' => 'array',
        'results' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class)
            ->whereIn('status', ['accepted', 'participating']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ActivityMessage::class);
    }

    /**
     * Scopes
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now());
    }

    /**
     * Mutators & Accessors
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            if (!$activity->access_code) {
                $activity->access_code = strtoupper(Str::random(6));
            }
        });
    }

    /**
     * Méthodes utilitaires
     */
    public function canJoin(User $user): bool
    {
        // Vérifier si l'utilisateur peut rejoindre l'activité
        if ($this->status !== 'open') {
            return false;
        }

        if ($this->participant_count >= $this->max_participants) {
            return false;
        }

        if ($this->is_private) {
            // Pour les activités privées, l'utilisateur doit être invité
            return $this->participants()
                ->where('user_id', $user->id)
                ->whereIn('status', ['invited', 'accepted'])
                ->exists();
        }

        // Pour les activités publiques, tout le monde peut rejoindre
        return true;
    }

    public function invite(User $user, User $invitedBy, string $message = null): ActivityParticipant
    {
        return $this->participants()->create([
            'user_id' => $user->id,
            'status' => 'invited',
            'invited_by' => $invitedBy->id,
            'invited_at' => now(),
            'invitation_message' => $message,
        ]);
    }

    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Mettre à jour le statut des participants acceptés
        $this->participants()
            ->where('status', 'accepted')
            ->update(['status' => 'participating', 'joined_at' => now()]);
    }

    public function complete(array $results = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'results' => $results,
        ]);

        // Mettre à jour le statut des participants
        $this->participants()
            ->where('status', 'participating')
            ->update(['status' => 'completed', 'left_at' => now()]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function isJoinable(): bool
    {
        return $this->status === 'open' &&
            $this->participant_count < $this->max_participants;
    }

    public function getFormattedDurationAttribute(): string
    {
        if (!$this->estimated_duration_minutes) {
            return 'Non définie';
        }

        $minutes = $this->estimated_duration_minutes;

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

    public function updateParticipantCount(): void
    {
        $count = $this->participants()
            ->whereIn('status', ['accepted', 'participating'])
            ->count();

        $this->update(['participant_count' => $count]);
    }

    public function generateNewAccessCode(): string
    {
        $newCode = strtoupper(Str::random(6));
        $this->update(['access_code' => $newCode]);
        return $newCode;
    }
}
