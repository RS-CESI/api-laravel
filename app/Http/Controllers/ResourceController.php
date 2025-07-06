<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResourceController extends Controller
{
    /**
     * Afficher les ressources publiques (sans authentification)
     */
    public function indexPublic(Request $request): JsonResponse
    {
        $query = Resource::with(['category', 'resourceType', 'creator:id,name', 'relationTypes'])
            ->published()
            ->public()
            ->orderBy('published_at', 'desc');

        // Filtres
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('resource_type_id')) {
            $query->where('resource_type_id', $request->resource_type_id);
        }

        if ($request->has('relation_type_id')) {
            $query->whereHas('relationTypes', function($q) use ($request) {
                $q->where('relation_types.id', $request->relation_type_id);
            });
        }

        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        if ($request->has('duration_max')) {
            $query->where('duration_minutes', '<=', $request->duration_max);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);

        $resources = $query->paginate($perPage);

        return response()->json($resources);
    }

    /**
     * Afficher une ressource publique (sans authentification)
     */
    public function showPublic(Resource $resource): JsonResponse
    {
        // Vérifier que la ressource est publique et publiée
        if ($resource->visibility !== 'public' || $resource->status !== 'published') {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $resource->load([
            'category',
            'resourceType',
            'creator:id,name',
            'relationTypes',
            'comments' => function($query) {
                $query->approved()->whereNull('parent_id')->with('replies.user:id,name')->latest();
            }
        ]);

        return response()->json($resource);
    }

    /**
     * Recherche de ressources
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (empty($search)) {
            return response()->json(['data' => [], 'message' => 'Search query is required']);
        }

        $query = Resource::with(['category', 'resourceType', 'creator:id,name'])
            ->published()
            ->public();

        // Recherche full-text si disponible, sinon LIKE
        if (config('database.default') === 'mysql') {
            $query->whereRaw('MATCH(title, description) AGAINST(? IN BOOLEAN MODE)', [$search]);
        } else {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $resources = $query->paginate(10);

        return response()->json($resources);
    }

    /**
     * Afficher une ressource (authentifié)
     */
    public function show(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $resource->load([
            'category',
            'resourceType',
            'creator:id,name',
            'validator:id,name',
            'relationTypes',
            'comments' => function($query) use ($user) {
                $query->approved()->whereNull('parent_id')->with('replies.user:id,name')->latest();
            }
        ]);

        // Ajouter les informations de progression de l'utilisateur
        $progression = $user->progressions()->where('resource_id', $resource->id)->first();
        $resource->user_progression = $progression;

        // Vérifier si en favoris
        $resource->is_favorite = $user->favorites()->where('resource_id', $resource->id)->exists();

        return response()->json($resource);
    }

    /**
     * Créer une nouvelle ressource
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'content' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'resource_type_id' => 'required|exists:resource_types,id',
            'visibility' => 'required|in:private,shared,public',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'external_url' => 'nullable|url',
            'relation_type_ids' => 'required|array|min:1',
            'relation_type_ids.*' => 'exists:relation_types,id',
            'file' => 'nullable|file|max:10240' // 10MB max
        ]);

        $user = auth()->user();

        // Générer le slug
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        $validated['created_by'] = $user->id;
        $validated['status'] = 'draft';

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

        // Attacher les types de relations
        $resource->relationTypes()->attach($validated['relation_type_ids']);

        $resource->load(['category', 'resourceType', 'relationTypes']);

        return response()->json($resource, 201);
    }

    /**
     * Mettre à jour une ressource
     */
    public function update(Request $request, Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canEdit($resource)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'content' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'resource_type_id' => 'sometimes|required|exists:resource_types,id',
            'visibility' => 'sometimes|required|in:private,shared,public',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            'difficulty_level' => 'sometimes|required|in:beginner,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'external_url' => 'nullable|url',
            'relation_type_ids' => 'sometimes|required|array|min:1',
            'relation_type_ids.*' => 'exists:relation_types,id',
            'file' => 'nullable|file|max:10240'
        ]);

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
            $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        }

        $resource->update($validated);

        // Mettre à jour les types de relations
        if (isset($validated['relation_type_ids'])) {
            $resource->relationTypes()->sync($validated['relation_type_ids']);
        }

        $resource->load(['category', 'resourceType', 'relationTypes']);

        return response()->json($resource);
    }

    /**
     * Supprimer une ressource
     */
    public function destroy(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canDelete($resource)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Supprimer le fichier associé
        if ($resource->file_path) {
            Storage::disk('public')->delete($resource->file_path);
        }

        $resource->delete();

        return response()->json(['message' => 'Resource deleted successfully']);
    }

    /**
     * Mes ressources
     */
    public function myResources(): JsonResponse
    {
        $user = auth()->user();

        $resources = Resource::with(['category', 'resourceType', 'relationTypes'])
            ->where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($resources);
    }

    /**
     * Mes brouillons
     */
    public function myDrafts(): JsonResponse
    {
        $user = auth()->user();

        $resources = Resource::with(['category', 'resourceType', 'relationTypes'])
            ->where('created_by', $user->id)
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($resources);
    }

    /**
     * Mes ressources publiées
     */
    public function myPublished(): JsonResponse
    {
        $user = auth()->user();

        $resources = Resource::with(['category', 'resourceType', 'relationTypes'])
            ->where('created_by', $user->id)
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return response()->json($resources);
    }

    /**
     * Incrémenter les vues
     */
    public function incrementView(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $resource->incrementViewCount();

        return response()->json(['message' => 'View count updated']);
    }

    /**
     * Incrémenter les téléchargements
     */
    public function incrementDownload(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions et la présence d'un fichier
        if (!$user->canView($resource) || !$resource->file_path) {
            return response()->json(['message' => 'Forbidden or no file'], 403);
        }

        $resource->incrementDownloadCount();

        return response()->json(['message' => 'Download count updated']);
    }

    /**
     * Télécharger le fichier d'une ressource
     */
    public function downloadFile(Resource $resource)
    {
        $user = auth()->user();

        // Vérifier les permissions et la présence d'un fichier
        if (!$user->canView($resource) || !$resource->file_path) {
            return response()->json(['message' => 'Forbidden or no file'], 403);
        }

        if (!Storage::disk('public')->exists($resource->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Incrémenter le compteur de téléchargements
        $resource->incrementDownloadCount();

        return Storage::disk('public')->download($resource->file_path, $resource->file_name);
    }

    /**
     * Soumettre une ressource pour validation
     */
    public function submitForValidation(Resource $resource): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canEdit($resource)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que la ressource est en brouillon
        if ($resource->status !== 'draft') {
            return response()->json(['message' => 'Only draft resources can be submitted'], 400);
        }

        // Valider que la ressource est complète
        if (empty($resource->title) || empty($resource->description) || empty($resource->content)) {
            return response()->json(['message' => 'Resource must have title, description and content'], 400);
        }

        $resource->update(['status' => 'pending']);

        return response()->json(['message' => 'Resource submitted for validation']);
    }
}
