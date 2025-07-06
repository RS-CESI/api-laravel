<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ModerationController extends Controller
{
    /**
     * Constructeur - Vérifier les permissions de modération
     */
    public function __construct()
    {
        $this->middleware('can.moderate');
    }

    /**
     * Afficher les ressources en attente de validation
     */
    public function pendingResources(Request $request): JsonResponse
    {
        $query = Resource::with([
            'category:id,name,color',
            'resourceType:id,name,icon',
            'creator:id,name,email',
            'relationTypes:id,name'
        ])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc');

        // Filtres
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('resource_type_id')) {
            $query->where('resource_type_id', $request->resource_type_id);
        }

        if ($request->has('created_since')) {
            $query->where('created_at', '>=', $request->created_since);
        }

        $resources = $query->paginate(15);

        // Statistiques pour le dashboard de modération
        $stats = [
            'total_pending' => Resource::where('status', 'pending')->count(),
            'oldest_pending' => Resource::where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->value('created_at'),
            'by_category' => Resource::where('status', 'pending')
                ->join('categories', 'resources.category_id', '=', 'categories.id')
                ->selectRaw('categories.name, COUNT(*) as count')
                ->groupBy('categories.id', 'categories.name')
                ->get(),
        ];

        return response()->json([
            'data' => $resources,
            'stats' => $stats,
            'message' => 'Pending resources retrieved successfully'
        ]);
    }

    /**
     * Approuver une ressource
     */
    public function approveResource(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que la ressource est en attente
        if ($resource->status !== 'pending') {
            return response()->json(['message' => 'Resource is not pending approval'], 422);
        }

        $validated = $request->validate([
            'validation_notes' => 'nullable|string|max:500',
        ]);

        // Approuver la ressource
        $resource->update([
            'status' => 'published',
            'validated_by' => $user->id,
            'validated_at' => now(),
            'published_at' => now(),
        ]);

        // Log de l'action de modération
        $this->logModerationAction($user, 'approve_resource', $resource, $validated['validation_notes'] ?? null);

        $resource->load(['category:id,name', 'creator:id,name']);

        return response()->json([
            'data' => $resource,
            'message' => 'Resource approved and published successfully'
        ]);
    }

    /**
     * Rejeter une ressource
     */
    public function rejectResource(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que la ressource est en attente
        if ($resource->status !== 'pending') {
            return response()->json(['message' => 'Resource is not pending approval'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        // Rejeter la ressource
        $resource->update([
            'status' => 'rejected',
            'validated_by' => $user->id,
            'validated_at' => now(),
        ]);

        // Log de l'action de modération
        $this->logModerationAction($user, 'reject_resource', $resource, $validated['rejection_reason']);

        $resource->load(['category:id,name', 'creator:id,name']);

        return response()->json([
            'data' => $resource,
            'message' => 'Resource rejected successfully'
        ]);
    }

    /**
     * Suspendre une ressource publiée
     */
    public function suspendResource(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que la ressource est publiée
        if ($resource->status !== 'published') {
            return response()->json(['message' => 'Only published resources can be suspended'], 422);
        }

        $validated = $request->validate([
            'suspension_reason' => 'required|string|max:500',
        ]);

        // Suspendre la ressource
        $resource->update([
            'status' => 'suspended',
            'validated_by' => $user->id,
            'validated_at' => now(),
        ]);

        // Log de l'action de modération
        $this->logModerationAction($user, 'suspend_resource', $resource, $validated['suspension_reason']);

        $resource->load(['category:id,name', 'creator:id,name']);

        return response()->json([
            'data' => $resource,
            'message' => 'Resource suspended successfully'
        ]);
    }

    /**
     * Réactiver une ressource suspendue
     */
    public function reactivateResource(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que la ressource est suspendue
        if ($resource->status !== 'suspended') {
            return response()->json(['message' => 'Resource is not suspended'], 422);
        }

        $validated = $request->validate([
            'reactivation_notes' => 'nullable|string|max:500',
        ]);

        // Réactiver la ressource
        $resource->update([
            'status' => 'published',
            'validated_by' => $user->id,
            'validated_at' => now(),
        ]);

        // Log de l'action de modération
        $this->logModerationAction($user, 'reactivate_resource', $resource, $validated['reactivation_notes'] ?? null);

        $resource->load(['category:id,name', 'creator:id,name']);

        return response()->json([
            'data' => $resource,
            'message' => 'Resource reactivated successfully'
        ]);
    }

    /**
     * Afficher les commentaires en attente de modération
     */
    public function pendingComments(Request $request): JsonResponse
    {
        $query = Comment::with([
            'resource:id,title',
            'user:id,name,email',
            'parent.user:id,name'
        ])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc');

        // Filtres
        if ($request->has('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        if ($request->has('created_since')) {
            $query->where('created_at', '>=', $request->created_since);
        }

        $comments = $query->paginate(20);

        // Statistiques pour le dashboard de modération
        $stats = [
            'total_pending' => Comment::where('status', 'pending')->count(),
            'oldest_pending' => Comment::where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->value('created_at'),
            'by_resource' => Comment::where('status', 'pending')
                ->join('resources', 'comments.resource_id', '=', 'resources.id')
                ->selectRaw('resources.title, COUNT(*) as count')
                ->groupBy('resources.id', 'resources.title')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'data' => $comments,
            'stats' => $stats,
            'message' => 'Pending comments retrieved successfully'
        ]);
    }

    /**
     * Approuver un commentaire
     */
    public function approveComment(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que le commentaire est en attente
        if ($comment->status !== 'pending') {
            return response()->json(['message' => 'Comment is not pending approval'], 422);
        }

        $validated = $request->validate([
            'moderation_notes' => 'nullable|string|max:500',
        ]);

        // Approuver le commentaire
        $comment->approve($user);

        // Log de l'action de modération
        $this->logModerationAction($user, 'approve_comment', $comment, $validated['moderation_notes'] ?? null);

        $comment->load(['resource:id,title', 'user:id,name']);

        return response()->json([
            'data' => $comment,
            'message' => 'Comment approved successfully'
        ]);
    }

    /**
     * Rejeter un commentaire
     */
    public function rejectComment(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que le commentaire est en attente
        if ($comment->status !== 'pending') {
            return response()->json(['message' => 'Comment is not pending approval'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        // Rejeter le commentaire
        $comment->reject($user, $validated['rejection_reason']);

        // Log de l'action de modération
        $this->logModerationAction($user, 'reject_comment', $comment, $validated['rejection_reason']);

        $comment->load(['resource:id,title', 'user:id,name']);

        return response()->json([
            'data' => $comment,
            'message' => 'Comment rejected successfully'
        ]);
    }

    /**
     * Masquer un commentaire (commentaire déjà approuvé)
     */
    public function hideComment(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que le commentaire est approuvé
        if ($comment->status !== 'approved') {
            return response()->json(['message' => 'Only approved comments can be hidden'], 422);
        }

        $validated = $request->validate([
            'hiding_reason' => 'required|string|max:500',
        ]);

        // Masquer le commentaire
        $comment->hide($user, $validated['hiding_reason']);

        // Log de l'action de modération
        $this->logModerationAction($user, 'hide_comment', $comment, $validated['hiding_reason']);

        $comment->load(['resource:id,title', 'user:id,name']);

        return response()->json([
            'data' => $comment,
            'message' => 'Comment hidden successfully'
        ]);
    }

    /**
     * Restaurer un commentaire masqué
     */
    public function restoreComment(Request $request, Comment $comment): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que le commentaire est masqué
        if ($comment->status !== 'hidden') {
            return response()->json(['message' => 'Comment is not hidden'], 422);
        }

        $validated = $request->validate([
            'restoration_notes' => 'nullable|string|max:500',
        ]);

        // Restaurer le commentaire
        $comment->update([
            'status' => 'approved',
            'moderated_by' => $user->id,
            'moderated_at' => now(),
            'moderation_reason' => null,
        ]);

        // Log de l'action de modération
        $this->logModerationAction($user, 'restore_comment', $comment, $validated['restoration_notes'] ?? null);

        $comment->load(['resource:id,title', 'user:id,name']);

        return response()->json([
            'data' => $comment,
            'message' => 'Comment restored successfully'
        ]);
    }

    /**
     * Dashboard de modération
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            // Statistiques des ressources
            'resources' => [
                'pending' => Resource::where('status', 'pending')->count(),
                'published_today' => Resource::where('status', 'published')
                    ->whereDate('published_at', today())
                    ->count(),
                'rejected_this_week' => Resource::where('status', 'rejected')
                    ->where('validated_at', '>=', now()->subWeek())
                    ->count(),
                'suspended' => Resource::where('status', 'suspended')->count(),
            ],

            // Statistiques des commentaires
            'comments' => [
                'pending' => Comment::where('status', 'pending')->count(),
                'approved_today' => Comment::where('status', 'approved')
                    ->whereDate('moderated_at', today())
                    ->count(),
                'rejected_this_week' => Comment::where('status', 'rejected')
                    ->where('moderated_at', '>=', now()->subWeek())
                    ->count(),
                'hidden' => Comment::where('status', 'hidden')->count(),
            ],

            // Activité de modération récente
            'recent_activity' => $this->getRecentModerationActivity(),

            // Modérateurs actifs
            'active_moderators' => $this->getActiveModerators(),

            // Temps de réponse moyen
            'response_times' => [
                'resources' => $this->getAverageResourceModerationTime(),
                'comments' => $this->getAverageCommentModerationTime(),
            ],
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Moderation dashboard retrieved successfully'
        ]);
    }

    /**
     * Historique des actions de modération
     */
    public function moderationHistory(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'action_type' => 'sometimes|in:approve_resource,reject_resource,suspend_resource,approve_comment,reject_comment,hide_comment',
            'moderator_id' => 'sometimes|exists:users,id',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        // Ici vous pourriez implémenter un système de logs de modération
        // Pour l'exemple, on retourne les actions récentes basées sur les timestamps

        $resourceActions = Resource::whereNotNull('validated_by')
            ->with(['validator:id,name', 'category:id,name'])
            ->when(isset($validated['moderator_id']), function($query) use ($validated) {
                $query->where('validated_by', $validated['moderator_id']);
            })
            ->when(isset($validated['date_from']), function($query) use ($validated) {
                $query->where('validated_at', '>=', $validated['date_from']);
            })
            ->when(isset($validated['date_to']), function($query) use ($validated) {
                $query->where('validated_at', '<=', $validated['date_to']);
            })
            ->orderBy('validated_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($resource) {
                return [
                    'type' => 'resource',
                    'action' => $resource->status === 'published' ? 'approved' : $resource->status,
                    'item_title' => $resource->title,
                    'moderator' => $resource->validator->name,
                    'moderated_at' => $resource->validated_at,
                ];
            });

        $commentActions = Comment::whereNotNull('moderated_by')
            ->with(['moderator:id,name', 'resource:id,title'])
            ->when(isset($validated['moderator_id']), function($query) use ($validated) {
                $query->where('moderated_by', $validated['moderator_id']);
            })
            ->when(isset($validated['date_from']), function($query) use ($validated) {
                $query->where('moderated_at', '>=', $validated['date_from']);
            })
            ->when(isset($validated['date_to']), function($query) use ($validated) {
                $query->where('moderated_at', '<=', $validated['date_to']);
            })
            ->orderBy('moderated_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($comment) {
                return [
                    'type' => 'comment',
                    'action' => $comment->status,
                    'item_title' => "Comment on: {$comment->resource->title}",
                    'moderator' => $comment->moderator->name,
                    'moderated_at' => $comment->moderated_at,
                ];
            });

        $allActions = $resourceActions->concat($commentActions)
            ->sortByDesc('moderated_at')
            ->take(100)
            ->values();

        return response()->json([
            'data' => $allActions,
            'message' => 'Moderation history retrieved successfully'
        ]);
    }

    /**
     * Méthodes privées pour les calculs
     */
    private function logModerationAction($user, $action, $item, $notes = null): void
    {
        // Ici, vous pourriez enregistrer dans une table de logs de modération
        Log::info('Moderation action', [
            'moderator_id' => $user->id,
            'moderator_name' => $user->name,
            'action' => $action,
            'item_type' => get_class($item),
            'item_id' => $item->id,
            'notes' => $notes,
            'timestamp' => now(),
        ]);
    }

    private function getRecentModerationActivity(): array
    {
        // Retourner les 10 dernières actions de modération
        $recentResources = Resource::whereNotNull('validated_by')
            ->with('validator:id,name')
            ->orderBy('validated_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'status', 'validated_by', 'validated_at'])
            ->map(function($resource) {
                return [
                    'type' => 'resource',
                    'title' => $resource->title,
                    'action' => $resource->status,
                    'moderator' => $resource->validator->name,
                    'date' => $resource->validated_at,
                ];
            });

        $recentComments = Comment::whereNotNull('moderated_by')
            ->with(['moderator:id,name', 'resource:id,title'])
            ->orderBy('moderated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($comment) {
                return [
                    'type' => 'comment',
                    'title' => "Comment on: {$comment->resource->title}",
                    'action' => $comment->status,
                    'moderator' => $comment->moderator->name,
                    'date' => $comment->moderated_at,
                ];
            });

        return $recentResources->concat($recentComments)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function getActiveModerators(): array
    {
        $resourceModerators = Resource::whereNotNull('validated_by')
            ->where('validated_at', '>=', now()->subMonth())
            ->selectRaw('validated_by, COUNT(*) as resources_moderated')
            ->groupBy('validated_by')
            ->with('validator:id,name')
            ->get();

        $commentModerators = Comment::whereNotNull('moderated_by')
            ->where('moderated_at', '>=', now()->subMonth())
            ->selectRaw('moderated_by, COUNT(*) as comments_moderated')
            ->groupBy('moderated_by')
            ->with('moderator:id,name')
            ->get();

        // Combiner les statistiques
        $moderators = [];
        foreach($resourceModerators as $mod) {
            $moderators[$mod->validated_by] = [
                'name' => $mod->validator->name,
                'resources' => $mod->resources_moderated,
                'comments' => 0,
            ];
        }

        foreach($commentModerators as $mod) {
            if(isset($moderators[$mod->moderated_by])) {
                $moderators[$mod->moderated_by]['comments'] = $mod->comments_moderated;
            } else {
                $moderators[$mod->moderated_by] = [
                    'name' => $mod->moderator->name,
                    'resources' => 0,
                    'comments' => $mod->comments_moderated,
                ];
            }
        }

        return array_values($moderators);
    }

    private function getAverageResourceModerationTime(): ?float
    {
        return Resource::whereNotNull('validated_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, validated_at)) as avg_hours')
            ->value('avg_hours');
    }

    private function getAverageCommentModerationTime(): ?float
    {
        return Comment::whereNotNull('moderated_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, moderated_at)) as avg_hours')
            ->value('avg_hours');
    }
}
