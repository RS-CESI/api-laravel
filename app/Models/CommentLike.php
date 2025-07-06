<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'comment_id',
        'user_id',
    ];

    /**
     * Relations
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeForComment($query, $commentId)
    {
        return $query->where('comment_id', $commentId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Boot method pour gérer les événements
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($like) {
            // Incrémenter le compteur de likes du commentaire
            $like->comment->increment('like_count');
        });

        static::deleted(function ($like) {
            // Décrémenter le compteur de likes du commentaire
            $like->comment->decrement('like_count');
        });
    }
}
