<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'resource_id',
        'user_id',
        'parent_id',
        'moderated_by',
        'status',
        'moderation_reason',
        'moderated_at',
        'is_pinned',
        'like_count',
        'reply_count',
        'edited_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'like_count' => 'integer',
        'reply_count' => 'integer',
        'moderated_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeParentComments($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeForResource($query, $resourceId)
    {
        return $query->where('resource_id', $resourceId);
    }

    /**
     * Méthodes utilitaires
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id &&
            $this->created_at->diffInMinutes(now()) <= 15; // 15 minutes pour éditer
    }

    public function canBeModeratedBy(User $user): bool
    {
        return in_array($user->role->name, ['moderator', 'administrator', 'super-administrator']);
    }

    public function approve(User $moderator): void
    {
        $this->update([
            'status' => 'approved',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_reason' => null,
        ]);
    }

    public function reject(User $moderator, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_reason' => $reason,
        ]);
    }

    public function hide(User $moderator, string $reason): void
    {
        $this->update([
            'status' => 'hidden',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_reason' => $reason,
        ]);
    }

    public function incrementReplyCount(): void
    {
        if ($this->parent_id) {
            $this->parent->increment('reply_count');
        }
    }

    public function decrementReplyCount(): void
    {
        if ($this->parent_id) {
            $this->parent->decrement('reply_count');
        }
    }

    /**
     * Boot method pour gérer les événements
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($comment) {
            if ($comment->parent_id) {
                $comment->incrementReplyCount();
            }
        });

        static::deleted(function ($comment) {
            if ($comment->parent_id) {
                $comment->decrementReplyCount();
            }
        });
    }
}
