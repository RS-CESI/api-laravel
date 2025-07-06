<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Resource;
use App\Models\CommentLike;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    /**
     * Afficher les commentaires d'une ressource
     */
    public function index(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur peut voir la ressource
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        // Charger les commentaires approuvés avec leurs réponses
        $comments = $resource->comments()
            ->approved()
            ->parentComments()
            ->with([
                'user:id,name',
                'replies' => function($query) {
                    $query->approved()
                        ->with('user:id,name')
                        ->orderBy('created_at', 'asc');
                }
            ])
            ->withCount('replies')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Ajouter les informations de likes pour l'utilisateur connecté
        foreach ($comments as $comment) {
            $comment->user_has_liked = $this->hasUserLikedComment($user, $comment);

            // Pour les réponses aussi
            if ($comment->replies) {
                foreach ($comment->replies as $reply) {
                    $reply->user_has_liked = $this->hasUserLikedComment($user, $reply);
                }
            }
        }

        return response()->json([
            'data' => $comments,
            'message' => 'Comments retrieved successfully',
            'meta' => [
                'resource_id' => $resource->id,
                'total_comments' => $resource->comments()->approved()->count()
            ]
        ]);
    }

    /**
     * Créer un nouveau commentaire
     */
    public function store(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur peut voir la ressource
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        // Vérifier que la ressource accepte les commentaires (publique et publiée)
        if ($resource->visibility !== 'public' || $resource->status !== 'published') {
            return response()->json(['message' => 'Comments not allowed on this resource'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:3|max:1000',
        ]);

        // Déterminer le statut selon les rôles
        $status = $user->canModerate() ? 'approved' : 'pending';

        $comment = Comment::create([
            'content' => $validated['content'],
            'resource_id' => $resource->id,
            'user_id' => $user->id,
            'status' => $status,
            'moderated_by' => $user->canModerate() ? $user->id : null,
            'moderated_at' => $user->canModerate() ? now() : null,
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'data' => $comment,
            'message' => $status === 'approved'
                ? 'Comment posted successfully'
                : 'Comment submitted for moderation',
            'status' => $status
        ], 201);
    }

    /**
     * Créer une réponse à un commentaire
     */
    public function reply(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que le commentaire parent est approuvé
        if (!$comment->isApproved()) {
            return response()->json(['message' => 'Cannot reply to unapproved comment'], 403);
        }

        // Vérifier l'accès à la ressource
        if (!$user->canView($comment->resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        // Ne pas permettre de répondre à une réponse (max 2 niveaux)
        if ($comment->isReply()) {
            return response()->json(['message' => 'Cannot reply to a reply'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:3|max:1000',
        ]);

        $status = $user->canModerate() ? 'approved' : 'pending';

        $reply = Comment::create([
            'content' => $validated['content'],
            'resource_id' => $comment->resource_id,
            'user_id' => $user->id,
            'parent_id' => $comment->id,
            'status' => $status,
            'moderated_by' => $user->canModerate() ? $user->id : null,
            'moderated_at' => $user->canModerate() ? now() : null,
        ]);

        $reply->load('user:id,name');

        return response()->json([
            'data' => $reply,
            'message' => $status === 'approved'
                ? 'Reply posted successfully'
                : 'Reply submitted for moderation',
            'status' => $status
        ], 201);
    }

    /**
     * Modifier un commentaire
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions d'édition
        if (!$comment->canBeEditedBy($user)) {
            return response()->json(['message' => 'Cannot edit this comment'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:3|max:1000',
        ]);

        $comment->edit($validated['content']);

        $comment->load('user:id,name');

        return response()->json([
            'data' => $comment,
            'message' => 'Comment updated successfully'
        ]);
    }

    /**
     * Supprimer un commentaire
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions de suppression
        if (!$comment->canBeDeletedBy($user)) {
            return response()->json(['message' => 'Cannot delete this comment'], 403);
        }

        // Supprimer le commentaire et ses réponses
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * Liker/Unliker un commentaire
     */
    public function toggleLike(Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que le commentaire est approuvé
        if (!$comment->isApproved()) {
            return response()->json(['message' => 'Cannot like unapproved comment'], 403);
        }

        // Vérifier l'accès à la ressource
        if (!$user->canView($comment->resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        // Vérifier si déjà liké
        $existingLike = CommentLike::where('comment_id', $comment->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingLike) {
            // Retirer le like
            $existingLike->delete();
            $comment->decrement('like_count');
            $action = 'unliked';
            $hasLiked = false;
        } else {
            // Ajouter le like
            CommentLike::create([
                'comment_id' => $comment->id,
                'user_id' => $user->id,
            ]);
            $comment->increment('like_count');
            $action = 'liked';
            $hasLiked = true;
        }

        return response()->json([
            'message' => "Comment {$action} successfully",
            'data' => [
                'comment_id' => $comment->id,
                'like_count' => $comment->fresh()->like_count,
                'user_has_liked' => $hasLiked,
                'action' => $action
            ]
        ]);
    }

    /**
     * Épingler un commentaire (modérateurs/admins)
     */
    public function pin(Comment $comment): JsonResponse
    {
        $user = auth()->user();

        if (!$user->canModerate()) {
            return response()->json(['message' => 'Forbidden - Moderation privileges required'], 403);
        }

        $comment->pin();

        return response()->json([
            'data' => $comment,
            'message' => 'Comment pinned successfully'
        ]);
    }

    /**
     * Désépingler un commentaire (modérateurs/admins)
     */
    public function unpin(Comment $comment): JsonResponse
    {
        $user = auth()->user();

        if (!$user->canModerate()) {
            return response()->json(['message' => 'Forbidden - Moderation privileges required'], 403);
        }

        $comment->unpin();

        return response()->json([
            'data' => $comment,
            'message' => 'Comment unpinned successfully'
        ]);
    }

    /**
     * Signaler un commentaire
     */
    public function report(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'reason' => 'required|string|in:spam,inappropriate,offensive,harassment,other',
            'details' => 'nullable|string|max:500'
        ]);

        // Ici vous pourriez implémenter un système de signalements
        // Pour l'instant, on log simplement l'action

        Log::info('Comment reported', [
            'comment_id' => $comment->id,
            'reported_by' => $user->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null
        ]);

        return response()->json([
            'message' => 'Comment reported successfully. Moderators will review it.'
        ]);
    }

    /**
     * Obtenir les commentaires en attente pour un utilisateur (ses propres commentaires)
     */
    public function pending(): JsonResponse
    {
        $user = auth()->user();

        $pendingComments = Comment::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['resource:id,title', 'parent.user:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $pendingComments,
            'message' => 'Pending comments retrieved successfully'
        ]);
    }

    /**
     * Statistiques des commentaires de l'utilisateur
     */
    public function userStats(): JsonResponse
    {
        $user = auth()->user();

        $stats = [
            'total_comments' => $user->comments()->count(),
            'approved_comments' => $user->comments()->where('status', 'approved')->count(),
            'pending_comments' => $user->comments()->where('status', 'pending')->count(),
            'rejected_comments' => $user->comments()->where('status', 'rejected')->count(),
            'total_likes_received' => $user->comments()->sum('like_count'),
            'recent_comments' => $user->comments()
                ->with('resource:id,title')
                ->latest()
                ->limit(5)
                ->get(['id', 'content', 'resource_id', 'status', 'created_at', 'like_count']),
            'most_liked_comment' => $user->comments()
                ->approved()
                ->orderBy('like_count', 'desc')
                ->first(['id', 'content', 'like_count', 'created_at'])
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'User comment statistics retrieved successfully'
        ]);
    }

    /**
     * Vérifier si l'utilisateur a liké un commentaire
     */
    private function hasUserLikedComment($user, Comment $comment): bool
    {
        return CommentLike::where('comment_id', $comment->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
