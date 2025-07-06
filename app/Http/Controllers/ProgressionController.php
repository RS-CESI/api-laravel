<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgressionController extends Controller
{
    /**
     * Afficher toutes les progressions de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = $user->progressions()
            ->with([
                'resource' => function($q) {
                    $q->select('id', 'title', 'description', 'category_id', 'resource_type_id', 'duration_minutes', 'difficulty_level')
                        ->with(['category:id,name,color,icon', 'resourceType:id,name,icon,color']);
                }
            ])
            ->orderBy('last_accessed_at', 'desc');

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->whereHas('resource', function($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        if ($request->has('difficulty_level')) {
            $query->whereHas('resource', function($q) use ($request) {
                $q->where('difficulty_level', $request->difficulty_level);
            });
        }

        // Pagination
        $progressions = $query->paginate(12);

        return response()->json([
            'data' => $progressions,
            'message' => 'Progressions retrieved successfully',
            'meta' => [
                'total_progressions' => $user->progressions()->count(),
                'completed_count' => $user->progressions()->completed()->count(),
                'in_progress_count' => $user->progressions()->inProgress()->count(),
            ]
        ]);
    }

    /**
     * Tableau de bord de progression
     */
    public function dashboard(): JsonResponse
    {
        $user = auth()->user();

        // Statistiques générales
        $stats = [
            'total_progressions' => $user->progressions()->count(),
            'completed' => $user->progressions()->completed()->count(),
            'in_progress' => $user->progressions()->inProgress()->count(),
            'bookmarked' => $user->progressions()->bookmarked()->count(),
            'paused' => $user->progressions()->where('status', 'paused')->count(),
            'total_time_spent' => $user->progressions()->sum('time_spent_minutes'),
            'average_completion_rate' => $user->progressions()->avg('progress_percentage'),
        ];

        // Progressions récentes (dernière activité)
        $recentProgressions = $user->progressions()
            ->with(['resource:id,title,category_id', 'resource.category:id,name,color'])
            ->recentlyAccessed(7)
            ->orderBy('last_accessed_at', 'desc')
            ->limit(5)
            ->get();

        // Ressources complétées récemment
        $recentCompletions = $user->progressions()
            ->completed()
            ->with(['resource:id,title,category_id', 'resource.category:id,name,color'])
            ->where('completed_at', '>=', now()->subDays(30))
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // Progressions par catégorie
        $progressionsByCategory = $user->progressions()
            ->join('resources', 'user_resource_progressions.resource_id', '=', 'resources.id')
            ->join('categories', 'resources.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, categories.color, categories.icon, COUNT(*) as count, AVG(progress_percentage) as avg_progress')
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderBy('count', 'desc')
            ->get();

        // Progressions par statut avec évolution
        $progressionsByStatus = $user->progressions()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        // Objectifs/recommandations
        $recommendations = $this->getRecommendations($user);

        return response()->json([
            'data' => [
                'stats' => $stats,
                'recent_progressions' => $recentProgressions,
                'recent_completions' => $recentCompletions,
                'by_category' => $progressionsByCategory,
                'by_status' => $progressionsByStatus,
                'recommendations' => $recommendations,
                'formatted_time_spent' => $this->formatTime($stats['total_time_spent']),
            ],
            'message' => 'Dashboard data retrieved successfully'
        ]);
    }

    /**
     * Créer ou mettre à jour une progression
     */
    public function createOrUpdate(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier l'accès à la ressource
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:not_started,in_progress,completed,paused,bookmarked',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'user_notes' => 'nullable|string|max:1000',
            'progress_data' => 'nullable|array',
        ]);

        $progression = $user->progressions()->updateOrCreate(
            ['resource_id' => $resource->id],
            array_merge($validated, [
                'last_accessed_at' => now(),
                'started_at' => $validated['status'] !== 'not_started' ? now() : null,
                'completed_at' => $validated['status'] === 'completed' ? now() : null,
            ])
        );

        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Progression updated successfully'
        ]);
    }

    /**
     * Mettre à jour une progression existante
     */
    public function update(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $progression = $user->progressions()->where('resource_id', $resource->id)->first();

        if (!$progression) {
            return response()->json(['message' => 'No progression found for this resource'], 404);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:not_started,in_progress,completed,paused,bookmarked',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'user_notes' => 'nullable|string|max:1000',
            'progress_data' => 'nullable|array',
            'user_rating' => 'nullable|integer|min:1|max:5',
            'user_review' => 'nullable|string|max:500',
        ]);

        // Gérer les dates selon le statut
        if (isset($validated['status'])) {
            switch ($validated['status']) {
                case 'completed':
                    $validated['completed_at'] = now();
                    $validated['progress_percentage'] = 100;
                    break;
                case 'in_progress':
                    if (!$progression->started_at) {
                        $validated['started_at'] = now();
                    }
                    break;
            }
        }

        $validated['last_accessed_at'] = now();

        $progression->update($validated);
        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Progression updated successfully'
        ]);
    }

    /**
     * Démarrer une ressource
     */
    public function start(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        $progression = $user->progressions()->updateOrCreate(
            ['resource_id' => $resource->id],
            [
                'status' => 'in_progress',
                'started_at' => now(),
                'last_accessed_at' => now(),
            ]
        );

        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Resource started successfully'
        ]);
    }

    /**
     * Marquer une ressource comme terminée
     */
    public function complete(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $progression = $user->progressions()->where('resource_id', $resource->id)->first();

        if (!$progression) {
            return response()->json(['message' => 'No progression found for this resource'], 404);
        }

        $validated = $request->validate([
            'user_rating' => 'nullable|integer|min:1|max:5',
            'user_review' => 'nullable|string|max:500',
            'progress_data' => 'nullable|array',
        ]);

        $progression->complete();

        if (!empty($validated)) {
            $progression->update($validated);
        }

        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Resource completed successfully'
        ]);
    }

    /**
     * Mettre une ressource de côté
     */
    public function bookmark(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        $progression = $user->progressions()->updateOrCreate(
            ['resource_id' => $resource->id],
            [
                'status' => 'bookmarked',
                'last_accessed_at' => now(),
            ]
        );

        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Resource bookmarked successfully'
        ]);
    }

    /**
     * Mettre en pause une ressource
     */
    public function pause(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $progression = $user->progressions()->where('resource_id', $resource->id)->first();

        if (!$progression) {
            return response()->json(['message' => 'No progression found for this resource'], 404);
        }

        $progression->pause();
        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Resource paused successfully'
        ]);
    }

    /**
     * Ajouter du temps passé sur une ressource
     */
    public function addTime(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:300', // Max 5h par session
        ]);

        $progression = $user->progressions()->where('resource_id', $resource->id)->first();

        if (!$progression) {
            // Créer une progression si elle n'existe pas
            $progression = $user->progressions()->create([
                'resource_id' => $resource->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'last_accessed_at' => now(),
                'time_spent_minutes' => $validated['minutes'],
            ]);
        } else {
            $progression->addTimeSpent($validated['minutes']);
        }

        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Time added successfully',
            'total_time' => $progression->formatted_time_spent
        ]);
    }

    /**
     * Noter une ressource
     */
    public function rate(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
        ]);

        $progression = $user->progressions()->where('resource_id', $resource->id)->first();

        if (!$progression) {
            return response()->json(['message' => 'No progression found for this resource'], 404);
        }

        $progression->rate($validated['rating'], $validated['review'] ?? null);
        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Resource rated successfully'
        ]);
    }

    /**
     * Mettre à jour le pourcentage de progression
     */
    public function updateProgress(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'progress_percentage' => 'required|integer|min:0|max:100',
            'progress_data' => 'nullable|array',
        ]);

        $progression = $user->progressions()->where('resource_id', $resource->id)->first();

        if (!$progression) {
            return response()->json(['message' => 'No progression found for this resource'], 404);
        }

        $progression->updateProgress(
            $validated['progress_percentage'],
            $validated['progress_data'] ?? null
        );

        $progression->load(['resource:id,title,category_id', 'resource.category:id,name,color']);

        return response()->json([
            'data' => $progression,
            'message' => 'Progress updated successfully'
        ]);
    }

    /**
     * Générer des recommandations pour l'utilisateur
     */
    private function getRecommendations($user): array
    {
        $recommendations = [];

        // Ressources en pause depuis longtemps
        $pausedResources = $user->progressions()
            ->where('status', 'paused')
            ->where('last_accessed_at', '<', now()->subDays(7))
            ->count();

        if ($pausedResources > 0) {
            $recommendations[] = [
                'type' => 'resume_paused',
                'message' => "Vous avez {$pausedResources} ressource(s) en pause. Pourquoi ne pas les reprendre ?",
                'action' => 'View paused resources'
            ];
        }

        // Ressources mises de côté
        $bookmarkedResources = $user->progressions()->bookmarked()->count();
        if ($bookmarkedResources > 0) {
            $recommendations[] = [
                'type' => 'start_bookmarked',
                'message' => "Vous avez {$bookmarkedResources} ressource(s) mise(s) de côté. Il est temps de commencer !",
                'action' => 'View bookmarked resources'
            ];
        }

        // Objectif de temps hebdomadaire
        $weeklyTime = $user->progressions()
            ->where('last_accessed_at', '>=', now()->subDays(7))
            ->sum('time_spent_minutes');

        if ($weeklyTime < 60) { // Moins d'1h par semaine
            $recommendations[] = [
                'type' => 'increase_time',
                'message' => 'Essayez de consacrer au moins 1h par semaine à vos ressources pour un meilleur apprentissage.',
                'action' => 'Find new resources'
            ];
        }

        return $recommendations;
    }

    /**
     * Formater le temps en heures et minutes
     */
    private function formatTime(int $minutes): string
    {
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
}
