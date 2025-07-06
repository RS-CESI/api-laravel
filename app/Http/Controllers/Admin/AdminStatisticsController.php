<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AdminStatisticsController extends Controller
{
    /**
     * Get dashboard statistics overview.
     */
    public function dashboard(Request $request)
    {
        $period = $request->get('period', '30'); // 7, 30, 90, 365 jours
        $startDate = Carbon::now()->subDays($period);

        // Statistiques générales
        $generalStats = [
            'total_users' => User::count(),
            'total_resources' => Resource::count(),
            'total_categories' => Category::count(),
            'active_categories' => Category::active()->count(),
        ];

        // Évolution sur la période
        $evolutionStats = [
            'new_users' => User::where('created_at', '>=', $startDate)->count(),
            'new_resources' => Resource::where('created_at', '>=', $startDate)->count(),
            'new_categories' => Category::where('created_at', '>=', $startDate)->count(),
        ];

        // Statistiques par jour sur la période
        $dailyStats = DB::table('users')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top catégories par nombre de ressources
        $topCategories = Category::withCount('resources')
            ->orderBy('resources_count', 'desc')
            ->take(10)
            ->get(['id', 'name', 'color', 'resources_count']);

        // Répartition des utilisateurs par rôle
        $usersByRole = User::select('role')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('role')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'general' => $generalStats,
                'evolution' => $evolutionStats,
                'daily_stats' => $dailyStats,
                'top_categories' => $topCategories,
                'users_by_role' => $usersByRole,
                'period' => $period,
            ],
            'message' => 'Statistiques du tableau de bord récupérées avec succès'
        ]);
    }

    /**
     * Get detailed resource statistics.
     */
    public function resourceStats(Request $request)
    {
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        // Statistiques générales des ressources
        $resourceStats = [
            'total' => Resource::count(),
            'published' => Resource::where('is_published', true)->count(),
            'draft' => Resource::where('is_published', false)->count(),
            'recent' => Resource::where('created_at', '>=', $startDate)->count(),
        ];

        // Ressources par catégorie
        $resourcesByCategory = Category::withCount('resources')
            ->having('resources_count', '>', 0)
            ->orderBy('resources_count', 'desc')
            ->get(['id', 'name', 'color', 'resources_count']);

        // Évolution des ressources par mois (12 derniers mois)
        $monthlyResources = Resource::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::createFromDate($item->year, $item->month, 1)->format('Y-m'),
                    'count' => $item->count,
                ];
            });

        // Ressources les plus récentes
        $recentResources = Resource::with('category:id,name,color')
            ->latest()
            ->take(10)
            ->get(['id', 'title', 'category_id', 'created_at', 'is_published']);

        // Statistiques par type de ressource (si vous avez un champ type)
        $resourcesByType = Resource::select('type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $resourceStats,
                'by_category' => $resourcesByCategory,
                'monthly_evolution' => $monthlyResources,
                'recent_resources' => $recentResources,
                'by_type' => $resourcesByType,
            ],
            'message' => 'Statistiques des ressources récupérées avec succès'
        ]);
    }

    /**
     * Get detailed user statistics.
     */
    public function userStats(Request $request)
    {
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        // Statistiques générales des utilisateurs
        $userStats = [
            'total' => User::count(),
            'active' => User::where('email_verified_at', '!=', null)->count(),
            'new_users' => User::where('created_at', '>=', $startDate)->count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
        ];

        // Répartition par rôle
        $usersByRole = User::select('role')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('role')
            ->get();

        // Évolution des inscriptions (12 derniers mois)
        $monthlyRegistrations = User::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::createFromDate($item->year, $item->month, 1)->format('Y-m'),
                    'count' => $item->count,
                ];
            });

        // Derniers utilisateurs inscrits
        $recentUsers = User::latest()
            ->take(10)
            ->get(['id', 'name', 'email', 'role', 'created_at', 'email_verified_at']);

        // Statistiques par domaine d'email
        $emailDomains = User::selectRaw('SUBSTRING_INDEX(email, "@", -1) as domain, COUNT(*) as count')
            ->groupBy('domain')
            ->orderBy('count', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $userStats,
                'by_role' => $usersByRole,
                'monthly_registrations' => $monthlyRegistrations,
                'recent_users' => $recentUsers,
                'email_domains' => $emailDomains,
            ],
            'message' => 'Statistiques des utilisateurs récupérées avec succès'
        ]);
    }

    /**
     * Get activity statistics.
     */
    public function activityStats(Request $request)
    {
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        // Activité récente (supposant qu'on ait une table activity_log ou similar)
        $recentActivity = [
            'total_actions' => 0, // À adapter selon votre système de logs
            'unique_users' => 0,
            'peak_hour' => '14:00', // Heure de pic d'activité
            'peak_day' => 'Mardi',
        ];

        // Activité par jour de la semaine
        $activityByDay = collect([
            ['day' => 'Lundi', 'count' => 0],
            ['day' => 'Mardi', 'count' => 0],
            ['day' => 'Mercredi', 'count' => 0],
            ['day' => 'Jeudi', 'count' => 0],
            ['day' => 'Vendredi', 'count' => 0],
            ['day' => 'Samedi', 'count' => 0],
            ['day' => 'Dimanche', 'count' => 0],
        ]);

        // Activité par heure
        $activityByHour = collect(range(0, 23))->map(function ($hour) {
            return [
                'hour' => sprintf('%02d:00', $hour),
                'count' => rand(0, 50), // À remplacer par de vraies données
            ];
        });

        // Actions les plus fréquentes
        $topActions = collect([
            ['action' => 'Consultation ressource', 'count' => 0],
            ['action' => 'Création ressource', 'count' => 0],
            ['action' => 'Modification profil', 'count' => 0],
            ['action' => 'Connexion', 'count' => 0],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $recentActivity,
                'by_day' => $activityByDay,
                'by_hour' => $activityByHour,
                'top_actions' => $topActions,
            ],
            'message' => 'Statistiques d\'activité récupérées avec succès'
        ]);
    }

    /**
     * Get engagement statistics.
     */
    public function engagementStats(Request $request)
    {
        $period = $request->get('period', '30');
        $startDate = Carbon::now()->subDays($period);

        // Métriques d'engagement
        $engagementMetrics = [
            'avg_session_duration' => '00:12:34', // Durée moyenne de session
            'pages_per_session' => 4.2,
            'bounce_rate' => 25.5, // Taux de rebond en %
            'return_rate' => 68.3, // Taux de retour en %
        ];

        // Ressources les plus consultées
        $topResources = Resource::with('category:id,name,color')
            ->orderBy('views_count', 'desc') // Supposant un champ views_count
            ->take(10)
            ->get(['id', 'title', 'category_id', 'views_count', 'created_at']);

        // Catégories les plus populaires
        $popularCategories = Category::withCount('resources')
            ->orderBy('resources_count', 'desc')
            ->take(10)
            ->get(['id', 'name', 'color', 'resources_count']);

        // Taux d'engagement par catégorie
        $engagementByCategory = Category::with('resources')
            ->get()
            ->map(function ($category) {
                return [
                    'category' => $category->name,
                    'color' => $category->color,
                    'resources_count' => $category->resources_count,
                    'avg_engagement' => rand(20, 80), // À remplacer par le vrai calcul
                ];
            });

        // Tendances d'engagement
        $engagementTrends = collect(range(0, 29))->map(function ($daysAgo) {
            $date = Carbon::now()->subDays($daysAgo);
            return [
                'date' => $date->format('Y-m-d'),
                'engagement_rate' => rand(40, 85), // À remplacer par de vraies données
            ];
        })->reverse()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $engagementMetrics,
                'top_resources' => $topResources,
                'popular_categories' => $popularCategories,
                'by_category' => $engagementByCategory,
                'trends' => $engagementTrends,
            ],
            'message' => 'Statistiques d\'engagement récupérées avec succès'
        ]);
    }

    /**
     * Export statistics data.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:dashboard,resources,users,activities,engagement',
            'format' => 'required|in:csv,json,pdf',
            'period' => 'integer|min:1|max:365',
        ]);

        $type = $validated['type'];
        $format = $validated['format'];
        $period = $validated['period'] ?? 30;

        // Récupérer les données selon le type
        switch ($type) {
            case 'dashboard':
                $data = $this->dashboard($request)->getData()->data;
                break;
            case 'resources':
                $data = $this->resourceStats($request)->getData()->data;
                break;
            case 'users':
                $data = $this->userStats($request)->getData()->data;
                break;
            case 'activities':
                $data = $this->activityStats($request)->getData()->data;
                break;
            case 'engagement':
                $data = $this->engagementStats($request)->getData()->data;
                break;
            default:
                $data = [];
        }

        $filename = "statistics_{$type}_" . Carbon::now()->format('Y-m-d_H-i-s');

        // Générer le fichier selon le format
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data, $filename);
            case 'json':
                return $this->exportToJson($data, $filename);
            case 'pdf':
                return $this->exportToPdf($data, $filename);
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Format d\'export non supporté'
                ], 400);
        }
    }

    /**
     * Export data to CSV format.
     */
    private function exportToCsv($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // Écrire les en-têtes
            fputcsv($file, ['Métrique', 'Valeur', 'Type']);

            // Écrire les données (logique simplifiée)
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        fputcsv($file, [$key . '_' . $subKey, $subValue, $key]);
                    }
                } else {
                    fputcsv($file, [$key, $value, 'général']);
                }
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export data to JSON format.
     */
    private function exportToJson($data, $filename)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
        ];

        $exportData = [
            'exported_at' => Carbon::now()->toISOString(),
            'data' => $data,
        ];

        return response()->json($exportData, 200, $headers);
    }

    /**
     * Export data to PDF format.
     */
    private function exportToPdf($data, $filename)
    {
        // Nécessite une librairie comme dompdf ou tcpdf
        // Exemple basique - à adapter selon votre setup

        return response()->json([
            'success' => false,
            'message' => 'Export PDF non encore implémenté. Utilisez CSV ou JSON.'
        ], 501);
    }
}
