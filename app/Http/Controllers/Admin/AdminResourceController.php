<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Category;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminResourceController extends Controller
{
    /**
     * Constructeur - Vérifier les permissions admin
     */
    public function __construct()
    {
        $this->middleware('role:administrator,super-administrator');
    }

    /**
     * Afficher toutes les ressources (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Resource::with([
            'category:id,name,color',
            'resourceType:id,name,icon',
            'creator:id,name,email',
            'validator:id,name',
            'relationTypes:id,name'
        ]);

        // Filtres avancés pour les admins
        if ($request->has('status')) {
            if ($request->status === 'all') {
                // Pas de filtre
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('resource_type_id')) {
            $query->where('resource_type_id', $request->resource_type_id);
        }

        if ($request->has('creator_id')) {
            $query->where('created_by', $request->creator_id);
        }

        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        if ($request->has('min_views')) {
            $query->where('view_count', '>=', $request->min_views);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhereHas('creator', function($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['created_at', 'updated_at', 'published_at', 'title', 'view_count', 'download_count', 'favorite_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $resources = $query->paginate($request->get('per_page', 15));

        // Statistiques pour le tableau de bord
        $stats = [
            'total' => Resource::count(),
            'published' => Resource::where('status', 'published')->count(),
            'pending' => Resource::where('status', 'pending')->count(),
            'draft' => Resource::where('status', 'draft')->count(),
            'rejected' => Resource::where('status', 'rejected')->count(),
            'suspended' => Resource::where('status', 'suspended')->count(),
        ];

        return response()->json([
            'data' => $resources,
            'stats' => $stats,
            'message' => 'Admin resources retrieved successfully'
        ]);
    }

    /**
     * Afficher une ressource spécifique (admin)
     */
    public function show(Resource $resource): JsonResponse
    {
        $resource->load([
            'category',
            'resourceType',
            'creator:id,name,email,created_at',
            'validator:id,name',
            'relationTypes',
            'comments.user:id,name',
            'progressions' => function($query) {
                $query->with('user:id,name')->latest()->limit(10);
            }
        ]);

        // Statistiques détaillées de la ressource
        $resource->detailed_stats = [
            'total_progressions' => $resource->progressions()->count(),
            'completed_progressions' => $resource->progressions()->completed()->count(),
            'average_rating' => $resource->progressions()->whereNotNull('user_rating')->avg('user_rating'),
            'total_time_spent' => $resource->progressions()->sum('time_spent_minutes'),
            'comments_count' => $resource->comments()->count(),
            'approved_comments' => $resource->comments()->approved()->count(),
            'recent_activity' => $resource->progressions()
                ->where('last_accessed_at', '>=', now()->subDays(30))
                ->count()
        ];

        return response()->json([
            'data' => $resource,
            'message' => 'Resource details retrieved successfully'
        ]);
    }

    /**
     * Créer une ressource (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'resource_type_id' => 'required|exists:resource_types,id',
            'created_by' => 'sometimes|exists:users,id', // Admin peut créer au nom d'un autre utilisateur
            'visibility' => 'required|in:private,shared,public',
            'status' => 'required|in:draft,pending,published',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'external_url' => 'nullable|url',
            'relation_type_ids' => 'required|array|min:1',
            'relation_type_ids.*' => 'exists:relation_types,id',
            'file' => 'nullable|file|max:10240'
        ]);

        $validated['created_by'] = $validated['created_by'] ?? $user->id;
        $validated['slug'] = \Str::slug($validated['title']) . '-' . \Str::random(6);

        // Si l'admin publie directement, marquer comme validé
        if ($validated['status'] === 'published') {
            $validated['validated_by'] = $user->id;
            $validated['validated_at'] = now();
            $validated['published_at'] = now();
        }

        // Gérer l'upload de fichier
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('resources', $filename, 'public');

            $validated['file_path'] = $path;
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_mime_type'] = $file->getMimeType();
            $validated['file_size'] = $file->getSize();
        }

        $resource = Resource::create($validated);
        $resource->relationTypes()->attach($validated['relation_type_ids']);

        $resource->load(['category', 'resourceType', 'creator', 'relationTypes']);

        return response()->json([
            'data' => $resource,
            'message' => 'Resource created successfully'
        ], 201);
    }

    /**
     * Mettre à jour une ressource (admin)
     */
    public function update(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'content' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'resource_type_id' => 'sometimes|required|exists:resource_types,id',
            'visibility' => 'sometimes|required|in:private,shared,public',
            'status' => 'sometimes|required|in:draft,pending,published,rejected,suspended',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            'difficulty_level' => 'sometimes|required|in:beginner,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'external_url' => 'nullable|url',
            'relation_type_ids' => 'sometimes|required|array|min:1',
            'relation_type_ids.*' => 'exists:relation_types,id',
            'file' => 'nullable|file|max:10240'
        ]);

        // Gérer les changements de statut
        if (isset($validated['status'])) {
            $oldStatus = $resource->status;
            $newStatus = $validated['status'];

            if ($oldStatus !== $newStatus) {
                $validated['validated_by'] = $user->id;
                $validated['validated_at'] = now();

                if ($newStatus === 'published' && $oldStatus !== 'published') {
                    $validated['published_at'] = now();
                }
            }
        }

        // Gérer l'upload de fichier
        if ($request->hasFile('file')) {
            // Supprimer l'ancien fichier
            if ($resource->file_path) {
                Storage::disk('public')->delete($resource->file_path);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('resources', $filename, 'public');

            $validated['file_path'] = $path;
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_mime_type'] = $file->getMimeType();
            $validated['file_size'] = $file->getSize();
        }

        // Mettre à jour le slug si le titre change
        if (isset($validated['title'])) {
            $validated['slug'] = \Str::slug($validated['title']) . '-' . \Str::random(6);
        }

        $resource->update($validated);

        // Mettre à jour les types de relations
        if (isset($validated['relation_type_ids'])) {
            $resource->relationTypes()->sync($validated['relation_type_ids']);
        }

        $resource->load(['category', 'resourceType', 'creator', 'validator', 'relationTypes']);

        return response()->json([
            'data' => $resource,
            'message' => 'Resource updated successfully'
        ]);
    }

    /**
     * Supprimer une ressource (admin)
     */
    public function destroy(Resource $resource): JsonResponse
    {
        // Supprimer le fichier associé
        if ($resource->file_path) {
            Storage::disk('public')->delete($resource->file_path);
        }

        // Sauvegarder les informations pour le log
        $resourceInfo = [
            'id' => $resource->id,
            'title' => $resource->title,
            'creator' => $resource->creator->name,
            'created_at' => $resource->created_at,
        ];

        $resource->delete();

        // Log de la suppression
        \Log::warning('Resource deleted by admin', [
            'admin_id' => auth()->user()->id,
            'admin_name' => auth()->user()->name,
            'deleted_resource' => $resourceInfo,
        ]);

        return response()->json([
            'message' => 'Resource deleted successfully'
        ]);
    }

    /**
     * Vue d'ensemble des statistiques pour admin
     */
    public function statsOverview(): JsonResponse
    {
        $stats = [
            // Statistiques générales
            'overview' => [
                'total_resources' => Resource::count(),
                'published_resources' => Resource::published()->count(),
                'pending_approval' => Resource::where('status', 'pending')->count(),
                'draft_resources' => Resource::where('status', 'draft')->count(),
                'rejected_resources' => Resource::where('status', 'rejected')->count(),
                'suspended_resources' => Resource::where('status', 'suspended')->count(),
            ],

            // Statistiques par période
            'by_period' => [
                'created_today' => Resource::whereDate('created_at', today())->count(),
                'created_this_week' => Resource::where('created_at', '>=', now()->subWeek())->count(),
                'created_this_month' => Resource::where('created_at', '>=', now()->subMonth())->count(),
                'published_today' => Resource::published()->whereDate('published_at', today())->count(),
                'published_this_week' => Resource::published()->where('published_at', '>=', now()->subWeek())->count(),
            ],

            // Top catégories
            'top_categories' => Category::withCount('resources')
                ->orderBy('resources_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'color']),

            // Top créateurs
            'top_creators' => User::withCount('resources')
                ->having('resources_count', '>', 0)
                ->orderBy('resources_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'email']),

            // Ressources les plus populaires
            'most_viewed' => Resource::published()
                ->orderBy('view_count', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'view_count', 'creator_id'])
                ->load('creator:id,name'),

            // Ressources récemment publiées
            'recently_published' => Resource::published()
                ->with('creator:id,name')
                ->orderBy('published_at', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'published_at', 'created_by']),

            // Métriques d'engagement
            'engagement' => [
                'total_views' => Resource::sum('view_count'),
                'total_downloads' => Resource::sum('download_count'),
                'total_favorites' => Resource::sum('favorite_count'),
                'average_rating' => Resource::whereNotNull('average_rating')->avg('average_rating'),
                'resources_with_comments' => Resource::has('comments')->count(),
            ],

            // Problèmes à surveiller
            'alerts' => [
                'old_pending' => Resource::where('status', 'pending')
                    ->where('created_at', '<', now()->subDays(7))
                    ->count(),
                'suspended_resources' => Resource::where('status', 'suspended')->count(),
                'resources_without_views' => Resource::published()
                    ->where('view_count', 0)
                    ->where('published_at', '<', now()->subDays(30))
                    ->count(),
            ],
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Admin statistics overview retrieved successfully'
        ]);
    }

    /**
     * Actions en lot sur les ressources
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'action' => 'required|in:publish,suspend,delete,change_category,change_visibility',
            'resource_ids' => 'required|array|min:1|max:50',
            'resource_ids.*' => 'exists:resources,id',
            'category_id' => 'required_if:action,change_category|exists:categories,id',
            'visibility' => 'required_if:action,change_visibility|in:private,shared,public',
        ]);

        $resources = Resource::whereIn('id', $validated['resource_ids'])->get();
        $results = [];

        foreach ($resources as $resource) {
            try {
                switch ($validated['action']) {
                    case 'publish':
                        if ($resource->status === 'pending' || $resource->status === 'draft') {
                            $resource->update([
                                'status' => 'published',
                                'validated_by' => $user->id,
                                'validated_at' => now(),
                                'published_at' => now(),
                            ]);
                            $results['success'][] = $resource->id;
                        } else {
                            $results['skipped'][] = $resource->id;
                        }
                        break;

                    case 'suspend':
                        if ($resource->status === 'published') {
                            $resource->update([
                                'status' => 'suspended',
                                'validated_by' => $user->id,
                                'validated_at' => now(),
                            ]);
                            $results['success'][] = $resource->id;
                        } else {
                            $results['skipped'][] = $resource->id;
                        }
                        break;

                    case 'delete':
                        if ($resource->file_path) {
                            Storage::disk('public')->delete($resource->file_path);
                        }
                        $resource->delete();
                        $results['success'][] = $resource->id;
                        break;

                    case 'change_category':
                        $resource->update(['category_id' => $validated['category_id']]);
                        $results['success'][] = $resource->id;
                        break;

                    case 'change_visibility':
                        $resource->update(['visibility' => $validated['visibility']]);
                        $results['success'][] = $resource->id;
                        break;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'resource_id' => $resource->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'data' => $results,
            'message' => 'Bulk action completed',
            'summary' => [
                'total_processed' => count($validated['resource_ids']),
                'successful' => count($results['success'] ?? []),
                'skipped' => count($results['skipped'] ?? []),
                'errors' => count($results['errors'] ?? []),
            ]
        ]);
    }

    /**
     * Exporter les données des ressources
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|in:csv,json',
            'status' => 'sometimes|in:all,published,pending,draft,rejected,suspended',
            'category_id' => 'sometimes|exists:categories,id',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        $query = Resource::with(['category:id,name', 'creator:id,name,email', 'resourceType:id,name']);

        // Appliquer les filtres
        if (isset($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to']);
        }

        $resources = $query->get()->map(function($resource) {
            return [
                'id' => $resource->id,
                'title' => $resource->title,
                'category' => $resource->category->name,
                'type' => $resource->resourceType->name,
                'creator' => $resource->creator->name,
                'creator_email' => $resource->creator->email,
                'status' => $resource->status,
                'visibility' => $resource->visibility,
                'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
                'published_at' => $resource->published_at?->format('Y-m-d H:i:s'),
                'view_count' => $resource->view_count,
                'download_count' => $resource->download_count,
                'favorite_count' => $resource->favorite_count,
                'average_rating' => $resource->average_rating,
            ];
        });

        return response()->json([
            'data' => $resources,
            'meta' => [
                'total_records' => $resources->count(),
                'exported_at' => now()->toISOString(),
                'filters_applied' => array_intersect_key($validated, array_flip(['status', 'category_id', 'date_from', 'date_to'])),
            ],
            'message' => 'Resources exported successfully'
        ]);
    }
}
