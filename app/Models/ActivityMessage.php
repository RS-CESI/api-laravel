<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'resource_activity_id',
        'user_id',
        'parent_id',
        'type',
        'recipient_id',
        'is_pinned',
        'is_read',
        'edited_at',
        'attachments',
        'metadata',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_read' => 'boolean',
        'attachments' => 'array',
        'metadata' => 'array',
        'edited_at' => 'datetime',
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

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ActivityMessage::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ActivityMessage::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * Scopes
     */
    public function scopePublic($query)
    {
        return $query->where('type', 'text')->whereNull('recipient_id');
    }

    public function scopePrivate($query)
    {
        return $query->where('type', 'private')->whereNotNull('recipient_id');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    public function scopeAnnouncements($query)
    {
        return $query->where('type', 'announcement');
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeParentMessages($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeForActivity($query, $activityId)
    {
        return $query->where('resource_activity_id', $activityId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('recipient_id', $userId);
        });
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Méthodes utilitaires
     */
    public function isPublic(): bool
    {
        return $this->type === 'text' && $this->recipient_id === null;
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private' && $this->recipient_id !== null;
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function isAnnouncement(): bool
    {
        return $this->type === 'announcement';
    }

    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id &&
            $this->created_at->diffInMinutes(now()) <= 15 && // 15 minutes pour éditer
            !$this->isSystem();
    }

    public function canBeDeletedBy(User $user): bool
    {
        // L'auteur peut supprimer son message ou le facilitateur peut supprimer n'importe quel message
        if ($this->user_id === $user->id) {
            return true;
        }

        // Vérifier si l'utilisateur est facilitateur de cette activité
        return $this->activity->participants()
            ->where('user_id', $user->id)
            ->where('role', 'facilitator')
            ->exists();
    }

    public function pin(): void
    {
        $this->update(['is_pinned' => true]);
    }

    public function unpin(): void
    {
        $this->update(['is_pinned' => false]);
    }

    public function markAsRead(): void
    {
        if ($this->isPrivate()) {
            $this->update(['is_read' => true]);
        }
    }

    public function edit(string $newContent): void
    {
        $this->update([
            'content' => $newContent,
            'edited_at' => now(),
        ]);
    }

    public function addReaction(User $user, string $reaction): void
    {
        $metadata = $this->metadata ?? [];
        $reactions = $metadata['reactions'] ?? [];

        if (!isset($reactions[$reaction])) {
            $reactions[$reaction] = [];
        }

        if (!in_array($user->id, $reactions[$reaction])) {
            $reactions[$reaction][] = $user->id;
        }

        $metadata['reactions'] = $reactions;
        $this->update(['metadata' => $metadata]);
    }

    public function removeReaction(User $user, string $reaction): void
    {
        $metadata = $this->metadata ?? [];
        $reactions = $metadata['reactions'] ?? [];

        if (isset($reactions[$reaction])) {
            $reactions[$reaction] = array_filter($reactions[$reaction], function($userId) use ($user) {
                return $userId !== $user->id;
            });

            if (empty($reactions[$reaction])) {
                unset($reactions[$reaction]);
            }
        }

        $metadata['reactions'] = $reactions;
        $this->update(['metadata' => $metadata]);
    }

    public function getReactions(): array
    {
        return $this->metadata['reactions'] ?? [];
    }

    public function hasReactionFrom(User $user, string $reaction): bool
    {
        $reactions = $this->getReactions();
        return isset($reactions[$reaction]) && in_array($user->id, $reactions[$reaction]);
    }

    /**
     * Créer un message système
     */
    public static function createSystemMessage(ResourceActivity $activity, string $content, array $metadata = null): self
    {
        return self::create([
            'content' => $content,
            'resource_activity_id' => $activity->id,
            'user_id' => $activity->created_by, // Le créateur de l'activité
            'type' => 'system',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Créer une annonce
     */
    public static function createAnnouncement(ResourceActivity $activity, User $user, string $content): self
    {
        return self::create([
            'content' => $content,
            'resource_activity_id' => $activity->id,
            'user_id' => $user->id,
            'type' => 'announcement',
            'is_pinned' => true,
        ]);
    }

    /**
     * Créer un message privé
     */
    public static function createPrivateMessage(ResourceActivity $activity, User $sender, User $recipient, string $content): self
    {
        return self::create([
            'content' => $content,
            'resource_activity_id' => $activity->id,
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'type' => 'private',
        ]);
    }
}
