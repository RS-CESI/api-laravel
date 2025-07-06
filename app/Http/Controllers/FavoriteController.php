<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    /**
     * Afficher tous les favoris de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = $user->favoriteResources()
            ->with([
                'category:id,name,color,icon',
                'resourceType:id,name,icon,color',
                'creator:id,name',
                'relationTypes:id,name'
            ])
            ->withPivot('created_at as favorited_at')
            ->orderBy('user_resource_favorites.created_at', 'desc');

        // Filtres optionnels
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('resource_type_id')) {
            $query->where('resource_type_id', $request->resource_type_id);
        }

        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        // Pagination
        $favorites = $query->paginate(12);

        // Ajouter la progression pour chaque ressource favorite
        $resourceIds = $favorites->pluck('id');
        $progressions = $user->progressions()
            ->whereIn('resource_id', $resourceIds)
            ->get()
            ->keyBy('resource_id');

        // Enrichir chaque ressource avec sa progression
        foreach ($favorites as $resource) {
            $resource->user_progression = $progressions->get($resource->id);
            $resource->is_favorite = true; // Toujours true ici
        }

        return response()->json([
            'data' => $favorites,
            'message' => 'Favorites retrieved successfully',
            'meta' => [
                'total_favorites' => $user->favorites()->count()
            ]
        ]);
    }

    /**
     * Ajouter/Retirer une ressource des favoris (toggle)
     */
    public function toggle(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur peut voir cette ressource
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        // Vérifier si déjà en favoris
        $favorite = $user->favorites()->where('resource_id', $resource->id)->first();

        if ($favorite) {
            // Retirer des favoris
            $favorite->delete();
            $action = 'removed';
            $isFavorite = false;
        } else {
            // Ajouter aux favoris
            $user->favorites()->create([
                'resource_id' => $resource->id
            ]);
            $action = 'added';
            $isFavorite = true;
        }

        // Recharger le compteur de favoris de la ressource
        $resource->refresh();

        return response()->json([
            'message' => "Resource {$action} to/from favorites successfully",
            'is_favorite' => $isFavorite,
            'favorite_count' => $resource->favorite_count,
            'data' => [
                'resource_id' => $resource->id,
                'action' => $action
            ]
        ]);
    }

    /**
     * Retirer une ressource des favoris
     */
    public function remove(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $favorite = $user->favorites()->where('resource_id', $resource->id)->first();

        if (!$favorite) {
            return response()->json(['message' => 'Resource not in favorites'], 404);
        }

        $favorite->delete();

        return response()->json([
            'message' => 'Resource removed from favorites successfully',
            'data' => [
                'resource_id' => $resource->id,
                'action' => 'removed'
            ]
        ]);
    }

    /**
     * Ajouter plusieurs ressources aux favoris
     */
    public function addMultiple(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'resource_ids' => 'required|array|min:1|max:20',
            'resource_ids.*' => 'exists:resources,id'
        ]);

        $resourceIds = $validated['resource_ids'];
        $added = [];
        $skipped = [];
        $forbidden = [];

        foreach ($resourceIds as $resourceId) {
            $resource = Resource::find($resourceId);

            // Vérifier les permissions
            if (!$user->canView($resource)) {
                $forbidden[] = $resourceId;
                continue;
            }

            // Vérifier si déjà en favoris
            if ($user->favorites()->where('resource_id', $resourceId)->exists()) {
                $skipped[] = $resourceId;
                continue;
            }

            // Ajouter aux favoris
            $user->favorites()->create(['resource_id' => $resourceId]);
            $added[] = $resourceId;
        }

        return response()->json([
            'message' => 'Bulk favorite operation completed',
            'data' => [
                'added' => $added,
                'skipped' => $skipped,
                'forbidden' => $forbidden,
                'summary' => [
                    'added_count' => count($added),
                    'skipped_count' => count($skipped),
                    'forbidden_count' => count($forbidden)
                ]
            ]
        ]);
    }

    /**
     * Retirer plusieurs ressources des favoris
     */
    public function removeMultiple(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'resource_ids' => 'required|array|min:1|max:20',
            'resource_ids.*' => 'exists:resources,id'
        ]);

        $resourceIds = $validated['resource_ids'];

        $removedCount = $user->favorites()
            ->whereIn('resource_id', $resourceIds)
            ->delete();

        return response()->json([
            'message' => 'Resources removed from favorites successfully',
            'data' => [
                'removed_count' => $removedCount,
                'requested_count' => count($resourceIds)
            ]
        ]);
    }

    /**
     * Vérifier si une ressource est en favoris
     */
    public function check(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que l'utilisateur peut voir cette ressource
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        $isFavorite = $user->favorites()->where('resource_id', $resource->id)->exists();
        $favoriteDate = null;

        if ($isFavorite) {
            $favorite = $user->favorites()->where('resource_id', $resource->id)->first();
            $favoriteDate = $favorite->created_at;
        }

        return response()->json([
            'is_favorite' => $isFavorite,
            'favorited_at' => $favoriteDate,
            'data' => [
                'resource_id' => $resource->id,
                'resource_title' => $resource->title
            ]
        ]);
    }

    /**
     * Statistiques des favoris de l'utilisateur
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();

        // Statistiques générales
        $totalFavorites = $user->favorites()->count();

        // Favoris par catégorie
        $favoritesByCategory = $user->favoriteResources()
            ->select('categories.name as category_name', 'categories.color', 'categories.icon')
            ->join('categories', 'resources.category_id', '=', 'categories.id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderBy('count', 'desc')
            ->get();

        // Favoris par type de ressource
        $favoritesByType = $user->favoriteResources()
            ->select('resource_types.name as type_name', 'resource_types.color', 'resource_types.icon')
            ->join('resource_types', 'resources.resource_type_id', '=', 'resource_types.id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('resource_types.id', 'resource_types.name', 'resource_types.color', 'resource_types.icon')
            ->orderBy('count', 'desc')
            ->get();

        // Favoris par niveau de difficulté
        $favoritesByDifficulty = $user->favoriteResources()
            ->selectRaw('difficulty_level, COUNT(*) as count')
            ->groupBy('difficulty_level')
            ->orderBy('count', 'desc')
            ->get();

        // Favoris récents (7 derniers jours)
        $recentFavorites = $user->favorites()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Progression sur les favoris
        $favoritesWithProgression = $user->favoriteResources()
            ->join('user_resource_progressions', function($join) use ($user) {
                $join->on('resources.id', '=', 'user_resource_progressions.resource_id')
                    ->where('user_resource_progressions.user_id', $user->id);
            })
            ->selectRaw('user_resource_progressions.status, COUNT(*) as count')
            ->groupBy('user_resource_progressions.status')
            ->get();

        return response()->json([
            'data' => [
                'total_favorites' => $totalFavorites,
                'recent_favorites' => $recentFavorites,
                'by_category' => $favoritesByCategory,
                'by_type' => $favoritesByType,
                'by_difficulty' => $favoritesByDifficulty,
                'with_progression' => $favoritesWithProgression,
                'completion_rate' => $totalFavorites > 0
                    ? round(($favoritesWithProgression->where('status', 'completed')->sum('count') / $totalFavorites) * 100, 2)
                    : 0
            ],
            'message' => 'Favorites statistics retrieved successfully'
        ]);
    }

    /**
     * Nettoyer les favoris (supprimer ceux de ressources supprimées)
     */
    public function cleanup(): JsonResponse
    {
        $user = auth()->user();

        // Trouver les favoris dont les ressources n'existent plus
        $orphanedFavorites = $user->favorites()
            ->whereNotIn('resource_id', function($query) {
                $query->select('id')->from('resources');
            })
            ->get();

        $cleanedCount = $orphanedFavorites->count();

        // Supprimer les favoris orphelins
        if ($cleanedCount > 0) {
            $user->favorites()
                ->whereIn('id', $orphanedFavorites->pluck('id'))
                ->delete();
        }

        return response()->json([
            'message' => 'Favorites cleanup completed',
            'data' => [
                'cleaned_count' => $cleanedCount,
                'remaining_favorites' => $user->favorites()->count()
            ]
        ]);
    }
}
