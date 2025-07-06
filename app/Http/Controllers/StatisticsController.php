<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\Category;
use App\Models\ResourceActivity;
use App\Models\User;
use App\Models\Comment;
use App\Models\UserResourceProgression;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Statistiques publiques (sans authentification)
     */
    public function publicStats(): JsonResponse
    {
        $stats = [
            // Compteurs généraux
            'total_resources' => Resource::published()->public()->count(),
            'total_categories' => Category::active()->count(),
            'total_users' => User::count(),
            'total_views' => Resource::published()->public()->sum('view_count'),
            'total_downloads' => Resource::published()->public()->sum('download_count'),

            // Ressources les plus populaires (publiques)
            'most_viewed_resources' => Resource::published()
                ->public()
                ->with(['category:id,name,color', 'resourceType:id,name,icon'])
                ->orderBy('view_count', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'view_count', 'category_id', 'resource_type_id']),

            // Catégories avec le plus de ressources
            'popular_categories' => Category::active()
                ->withCount(['resources' => function($query) {
                    $query->published()->public();
                }])
                ->orderBy('resources_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'color', 'icon']),

            // Ressources récentes
            'recent_resources' => Resource::published()
                ->public()
                ->with(['category:id,name,color', 'creator:id,name'])
                ->orderBy('published_at', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'published_at', 'category_id', 'created_by']),

            // Activités publiques ouvertes
            'upcoming_activities' => ResourceActivity::where('is_private', false)
                ->where('status', 'open')
                ->where('scheduled_at', '>', now())
                ->with(['resource:id,title', 'creator:id,name'])
                ->orderBy('scheduled_at', 'asc')
                ->limit(3)
                ->get(['id', 'title', 'scheduled_at', 'resource_id', 'created_by', 'participant_count', 'max_participants']),
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Public statistics retrieved successfully',
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Statistiques par catégorie
     */
    public function categoryStats(): JsonResponse
    {
        $user = auth()->user();

        $categories = Category::active()
            ->withCount([
                'resources as total_resources' => function($query) use ($user) {
                    if ($user && $user->isAdmin()) {
                        $query->whereIn('status', ['published', 'pending', 'draft']);
                    } else {
                        $query->published()->whereIn('visibility', ['public', 'shared']);
                    }
                },
                'resources as published_resources' => function($query) {
                    $query->published()->whereIn('visibility', ['public', 'shared']);
                }
            ])
            ->get()
            ->map(function($category) use ($user) {
                // Statistiques détaillées par catégorie
                $resourceQuery = $category->resources();

                if (!$user || !$user->isAdmin()) {
                    $resourceQuery->published()->whereIn('visibility', ['public', 'shared']);
                }

                $resources = $resourceQuery->get();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'total_resources' => $resources->count(),
                    'published_resources' => $resources->where('status', 'published')->count(),
                    'total_views' => $resources->sum('view_count'),
                    'total_downloads' => $resources->sum('download_count'),
                    'total_favorites' => $resources->sum('favorite_count'),
                    'average_rating' => $resources->whereNotNull('average_rating')->avg('average_rating'),
                    'difficulty_distribution' => $resources->groupBy('difficulty_level')
                        ->map->count()
                        ->toArray(),
                ];
            });

        return response()->json([
            'data' => $categories,
            'message' => 'Category statistics retrieved successfully'
        ]);
    }

    /**
     * Ressources populaires avec filtres
     */
    public function popularResources(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'period' => 'sometimes|in:week,month,quarter,year,all',
            'category_id' => 'sometimes|exists:categories,id',
            'resource_type_id' => 'sometimes|exists:resource_types,id',
            'metric' => 'sometimes|in:views,downloads,favorites,rating',
            'limit' => 'sometimes|integer|min:5|max:50',
        ]);

        $period = $validated['period'] ?? 'month';
        $metric = $validated['metric'] ?? 'views';
        $limit = $validated['limit'] ?? 10;

        $query = Resource::with([
            'category:id,name,color,icon',
            'resourceType:id,name,icon',
            'creator:id,name'
        ]);

        // Appliquer les permissions
        if (!$user || !$user->isAdmin()) {
            $query->published()->whereIn('visibility', ['public', 'shared']);
        }

        // Filtres
        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['resource_type_id'])) {
            $query->where('resource_type_id', $validated['resource_type_id']);
        }

        // Filtre de période
        if ($period !== 'all') {
            $date = match($period) {
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                'quarter' => now()->subMonths(3),
                'year' => now()->subYear(),
            };
            $query->where('published_at', '>=', $date);
        }

        // Tri par métrique
        $orderBy = match($metric) {
            'views' => 'view_count',
            'downloads' => 'download_count',
            'favorites' => 'favorite_count',
            'rating' => 'average_rating',
        };

        $resources = $query->orderBy($orderBy, 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $resources,
            'meta' => [
                'period' => $period,
                'metric' => $metric,
                'limit' => $limit,
                'filters' => [
                    'category_id' => $validated['category_id'] ?? null,
                    'resource_type_id' => $validated['resource_type_id'] ?? null,
                ]
            ],
            'message' => 'Popular resources retrieved successfully'
        ]);
    }

    /**
     * Tendances d'utilisation
     */
    public function trends(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $validated = $request->validate([
            'period' => 'sometimes|in:week,month,quarter',
            'metric' => 'sometimes|in:resources,users,activities,engagement',
        ]);

        $period = $validated['period'] ?? 'month';
        $metric = $validated['metric'] ?? 'resources';

        $endDate = now();
        $startDate = match($period) {
            'week' => $endDate->copy()->subWeeks(12), // 12 semaines
            'month' => $endDate->copy()->subMonths(12), // 12 mois
            'quarter' => $endDate->copy()->subMonths(24), // 8 trimestres
        };

        $dateFormat = match($period) {
            'week' => '%Y-%u', // Année-semaine
            'month' => '%Y-%m', // Année-mois
            'quarter' => 'CONCAT(YEAR(created_at), "-Q", QUARTER(created_at))',
        };

        $trends = match($metric) {
            'resources' => $this->getResourceTrends($startDate, $endDate, $dateFormat),
            'users' => $this->getUserTrends($startDate, $endDate, $dateFormat),
            'activities' => $this->getActivityTrends($startDate, $endDate, $dateFormat),
            'engagement' => $this->getEngagementTrends($startDate, $endDate, $dateFormat),
        };

        return response()->json([
            'data' => $trends,
            'meta' => [
                'period' => $period,
                'metric' => $metric,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'message' => 'Trends retrieved successfully'
        ]);
    }

    /**
     * Statistiques d'engagement utilisateur
     */
    public function engagementStats(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $stats = [
            // Utilisateurs actifs
            'active_users' => [
                'daily' => User::whereHas('progressions', function($query) {
                    $query->where('last_accessed_at', '>=', now()->subDay());
                })->count(),
                'weekly' => User::whereHas('progressions', function($query) {
                    $query->where('last_accessed_at', '>=', now()->subWeek());
                })->count(),
                'monthly' => User::whereHas('progressions', function($query) {
                    $query->where('last_accessed_at', '>=', now()->subMonth());
                })->count(),
            ],

            // Progressions
            'progressions' => [
                'total' => UserResourceProgression::count(),
                'completed_this_month' => UserResourceProgression::completed()
                    ->where('completed_at', '>=', now()->subMonth())
                    ->count(),
                'average_completion_rate' => UserResourceProgression::avg('progress_percentage'),
                'by_status' => UserResourceProgression::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
            ],

            // Activités collaboratives
            'activities' => [
                'total' => ResourceActivity::count(),
                'active' => ResourceActivity::whereIn('status', ['open', 'in_progress'])->count(),
                'completed_this_month' => ResourceActivity::completed()
                    ->where('completed_at', '>=', now()->subMonth())
                    ->count(),
                'average_participants' => ResourceActivity::avg('participant_count'),
            ],

            // Commentaires
            'comments' => [
                'total' => Comment::approved()->count(),
                'this_month' => Comment::approved()
                    ->where('created_at', '>=', now()->subMonth())
                    ->count(),
                'average_per_resource' => Comment::approved()->count() /
                    max(Resource::published()->count(), 1),
            ],

            // Temps d'utilisation
            'time_usage' => [
                'total_minutes' => UserResourceProgression::sum('time_spent_minutes'),
                'average_session' => UserResourceProgression::where('time_spent_minutes', '>', 0)
                    ->avg('time_spent_minutes'),
                'this_week' => UserResourceProgression::where('last_accessed_at', '>=', now()->subWeek())
                    ->sum('time_spent_minutes'),
            ],
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Engagement statistics retrieved successfully'
        ]);
    }

    /**
     * Métriques de performance
     */
    public function performanceMetrics(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $metrics = [
            // Taux de conversion
            'conversion_rates' => [
                'resource_to_progression' => $this->calculateConversionRate(
                    Resource::published()->sum('view_count'),
                    UserResourceProgression::count()
                ),
                'progression_to_completion' => $this->calculateConversionRate(
                    UserResourceProgression::count(),
                    UserResourceProgression::completed()->count()
                ),
                'invitation_to_participation' => $this->calculateConversionRate(
                    DB::table('activity_participants')->where('status', 'invited')->count(),
                    DB::table('activity_participants')->whereIn('status', ['accepted', 'participating'])->count()
                ),
            ],

            // Temps moyen
            'average_times' => [
                'time_to_start' => $this->getAverageTimeToStart(),
                'time_to_complete' => $this->getAverageTimeToComplete(),
                'session_duration' => UserResourceProgression::where('time_spent_minutes', '>', 0)
                    ->avg('time_spent_minutes'),
            ],

            // Qualité du contenu
            'content_quality' => [
                'average_rating' => Resource::whereNotNull('average_rating')->avg('average_rating'),
                'resources_with_ratings' => Resource::whereNotNull('average_rating')->count(),
                'high_rated_resources' => Resource::where('average_rating', '>=', 4)->count(),
                'most_favorited' => Resource::orderBy('favorite_count', 'desc')
                    ->first(['id', 'title', 'favorite_count']),
            ],

            // Rétention utilisateur
            'retention' => [
                'returning_users_week' => $this->getReturningUsers(7),
                'returning_users_month' => $this->getReturningUsers(30),
                'user_lifecycle' => $this->getUserLifecycleStats(),
            ],
        ];

        return response()->json([
            'data' => $metrics,
            'message' => 'Performance metrics retrieved successfully'
        ]);
    }

    /**
     * Méthodes privées pour les calculs
     */
    private function getResourceTrends($startDate, $endDate, $dateFormat): array
    {
        return Resource::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as count")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    private function getUserTrends($startDate, $endDate, $dateFormat): array
    {
        return User::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as count")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    private function getActivityTrends($startDate, $endDate, $dateFormat): array
    {
        return ResourceActivity::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as count")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    private function getEngagementTrends($startDate, $endDate, $dateFormat): array
    {
        return UserResourceProgression::selectRaw("DATE_FORMAT(last_accessed_at, '{$dateFormat}') as period, COUNT(DISTINCT user_id) as active_users, SUM(time_spent_minutes) as total_time")
            ->whereBetween('last_accessed_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    private function calculateConversionRate($total, $converted): float
    {
        return $total > 0 ? round(($converted / $total) * 100, 2) : 0;
    }

    private function getAverageTimeToStart(): ?float
    {
        return UserResourceProgression::whereNotNull('started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)) as avg_minutes')
            ->value('avg_minutes');
    }

    private function getAverageTimeToComplete(): ?float
    {
        return UserResourceProgression::whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_minutes')
            ->value('avg_minutes');
    }

    private function getReturningUsers(int $days): int
    {
        return User::whereHas('progressions', function($query) use ($days) {
            $query->where('last_accessed_at', '>=', now()->subDays($days));
        })
            ->whereHas('progressions', function($query) use ($days) {
                $query->where('last_accessed_at', '<', now()->subDays($days));
            })
            ->count();
    }

    private function getUserLifecycleStats(): array
    {
        return [
            'new_users' => User::where('created_at', '>=', now()->subMonth())->count(),
            'active_users' => User::whereHas('progressions', function($query) {
                $query->where('last_accessed_at', '>=', now()->subMonth());
            })->count(),
            'inactive_users' => User::whereDoesntHave('progressions', function($query) {
                $query->where('last_accessed_at', '>=', now()->subMonths(3));
            })->count(),
        ];
    }
}
