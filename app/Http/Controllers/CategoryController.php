<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Afficher les catégories publiques (pour filtres, sans authentification)
     */
    public function indexPublic(): JsonResponse
    {
        $categories = Category::active()
            ->ordered()
            ->withCount(['resources' => function($query) {
                $query->published()->public();
            }])
            ->get();

        return response()->json([
            'data' => $categories,
            'message' => 'Categories retrieved successfully'
        ]);
    }

    /**
     * Afficher toutes les catégories (authentifié)
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $query = Category::ordered();

        // Si pas admin, afficher seulement les actives
        if (!$user->isAdmin()) {
            $query->active();
        }

        $categories = $query->withCount(['resources' => function($resourceQuery) use ($user) {
            if ($user->isAdmin()) {
                // Admins voient toutes les ressources
                $resourceQuery->whereIn('status', ['draft', 'pending', 'published', 'rejected', 'suspended']);
            } else {
                // Utilisateurs normaux voient leurs ressources + publiques
                $resourceQuery->where(function($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhere(function($subQ) {
                            $subQ->where('status', 'published')
                                ->whereIn('visibility', ['public', 'shared']);
                        });
                });
            }
        }])->get();

        return response()->json([
            'data' => $categories,
            'message' => 'Categories retrieved successfully'
        ]);
    }

    /**
     * Afficher une catégorie spécifique
     */
    public function show(Category $category): JsonResponse
    {
        $user = auth()->user();

        // Si pas admin et catégorie inactive, refuser
        if (!$category->is_active && !$user->isAdmin()) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Charger les ressources selon les permissions
        $category->load(['resources' => function($query) use ($user) {
            if ($user->isAdmin()) {
                $query->with(['resourceType', 'creator:id,name'])
                    ->orderBy('created_at', 'desc');
            } else {
                $query->where(function($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhere(function($subQ) {
                            $subQ->where('status', 'published')
                                ->whereIn('visibility', ['public', 'shared']);
                        });
                })->with(['resourceType', 'creator:id,name'])
                    ->orderBy('created_at', 'desc');
            }
        }]);

        return response()->json([
            'data' => $category,
            'message' => 'Category retrieved successfully'
        ]);
    }

    /**
     * Afficher une catégorie publique par ID (sans authentification)
     */
    public function showPublic(Category $category): JsonResponse
    {
        // Ne montrer que si la catégorie est active
        if (!$category->is_active) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Charger les ressources publiques seulement
        $category->load(['resources' => function($query) {
            $query->where('status', 'published')
                ->whereIn('visibility', ['public', 'shared'])
                ->with(['resourceType', 'creator:id,name'])
                ->orderBy('created_at', 'desc');
        }]);

        return response()->json([
            'data' => $category,
            'message' => 'Category retrieved successfully'
        ]);
    }


    /**
     * Créer une nouvelle catégorie (Admins seulement)
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:500',
            'color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        // Valeurs par défaut
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $category = Category::create($validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully'
        ], 201);
    }

    /**
     * Mettre à jour une catégorie (Admins seulement)
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:500',
            'color' => 'sometimes|required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        $category->update($validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category updated successfully'
        ]);
    }

    /**
     * Supprimer une catégorie (Admins seulement)
     */
    public function destroy(Category $category): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        // Vérifier s'il y a des ressources liées
        $resourceCount = $category->resources()->count();

        if ($resourceCount > 0) {
            return response()->json([
                'message' => 'Cannot delete category with existing resources',
                'resource_count' => $resourceCount
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Activer une catégorie (Admins seulement)
     */
    public function activate(Category $category): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $category->update(['is_active' => true]);

        return response()->json([
            'data' => $category,
            'message' => 'Category activated successfully'
        ]);
    }

    /**
     * Désactiver une catégorie (Admins seulement)
     */
    public function deactivate(Category $category): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $category->update(['is_active' => false]);

        return response()->json([
            'data' => $category,
            'message' => 'Category deactivated successfully'
        ]);
    }

    /**
     * Réorganiser l'ordre des catégories (Admins seulement)
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer|min:0'
        ]);

        foreach ($validated['categories'] as $categoryData) {
            Category::where('id', $categoryData['id'])
                ->update(['sort_order' => $categoryData['sort_order']]);
        }

        return response()->json([
            'message' => 'Categories reordered successfully'
        ]);
    }

    /**
     * Statistiques d'une catégorie (Admins)
     */
    public function statistics(Category $category): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden - Admin access required'], 403);
        }

        $stats = [
            'total_resources' => $category->resources()->count(),
            'published_resources' => $category->resources()->where('status', 'published')->count(),
            'draft_resources' => $category->resources()->where('status', 'draft')->count(),
            'pending_resources' => $category->resources()->where('status', 'pending')->count(),
            'total_views' => $category->resources()->sum('view_count'),
            'total_downloads' => $category->resources()->sum('download_count'),
            'total_favorites' => $category->resources()->sum('favorite_count'),
            'average_rating' => $category->resources()
                ->whereNotNull('average_rating')
                ->avg('average_rating'),
            'most_viewed_resource' => $category->resources()
                ->orderBy('view_count', 'desc')
                ->first(['id', 'title', 'view_count']),
            'recent_resources' => $category->resources()
                ->with('creator:id,name')
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'created_by', 'created_at', 'status'])
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Category statistics retrieved successfully'
        ]);
    }

    /**
     * Rechercher des catégories
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (empty($search)) {
            return $this->index();
        }

        $user = auth()->user();
        $query = Category::where('name', 'LIKE', "%{$search}%")
            ->orWhere('description', 'LIKE', "%{$search}%");

        // Si pas admin, seulement les actives
        if (!$user->isAdmin()) {
            $query->active();
        }

        $categories = $query->ordered()
            ->withCount('resources')
            ->get();

        return response()->json([
            'data' => $categories,
            'message' => 'Categories search completed'
        ]);
    }
}
