<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Recherche
        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        // Filtrage par statut
        if ($request->has('status') && $request->status != '') {
            $query->where('is_active', $request->status === 'active');
        }

        // Tri par défaut avec scope
        $categories = $query->ordered()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Catégories récupérées avec succès'
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Définir sort_order par défaut si non fourni
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = Category::max('sort_order') + 1;
        }

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Catégorie créée avec succès'
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        $category->load('resources');

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Catégorie récupérée avec succès'
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category->id)
            ],
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Catégorie mise à jour avec succès'
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category)
    {
        // Vérifier s'il y a des ressources associées
        if ($category->resources()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une catégorie qui contient des ressources'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée avec succès'
        ]);
    }

    /**
     * Activate the specified category.
     */
    public function activate(Category $category)
    {
        $category->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Catégorie activée avec succès'
        ]);
    }

    /**
     * Deactivate the specified category.
     */
    public function deactivate(Category $category)
    {
        $category->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
            'message' => 'Catégorie désactivée avec succès'
        ]);
    }

    /**
     * Get active categories for dropdown/select.
     */
    public function getActiveCategories()
    {
        $categories = Category::active()->ordered()->get(['id', 'name', 'color', 'icon']);

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Catégories actives récupérées avec succès'
        ]);
    }

    /**
     * Update sort order for multiple categories.
     */
    public function updateSortOrder(Request $request)
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['categories'] as $categoryData) {
            Category::where('id', $categoryData['id'])
                ->update(['sort_order' => $categoryData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre des catégories mis à jour avec succès'
        ]);
    }

    /**
     * Get categories statistics.
     */
    public function getStats()
    {
        $stats = [
            'total' => Category::count(),
            'active' => Category::active()->count(),
            'inactive' => Category::where('is_active', false)->count(),
            'with_resources' => Category::has('resources')->count(),
            'without_resources' => Category::doesntHave('resources')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistiques des catégories récupérées avec succès'
        ]);
    }
}
