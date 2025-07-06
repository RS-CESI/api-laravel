<?php

namespace App\Http\Controllers;

use App\Models\ResourceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResourceTypeController extends Controller
{
    /**
     * Display a listing of active resource types for public access.
     */
    public function indexPublic()
    {
        $resourceTypes = ResourceType::active()
            ->ordered()
            ->select('id', 'name', 'description', 'icon', 'color', 'requires_file', 'allowed_file_types')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $resourceTypes,
            'message' => 'Types de ressources récupérés avec succès'
        ]);
    }

    /**
     * Display a listing of all resource types (admin).
     */
    public function index(Request $request)
    {
        $query = ResourceType::query()->withCount('resources');

        // Recherche
        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        // Filtrage par statut
        if ($request->has('status') && $request->status != '') {
            $query->where('is_active', $request->status === 'active');
        }

        // Filtrage par requirement de fichier
        if ($request->has('requires_file') && $request->requires_file != '') {
            $query->where('requires_file', $request->requires_file === 'true');
        }

        // Tri par défaut avec scope
        $resourceTypes = $query->ordered()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $resourceTypes,
            'message' => 'Types de ressources récupérés avec succès'
        ]);
    }

    /**
     * Store a newly created resource type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:resource_types,name',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'is_active' => 'boolean',
            'requires_file' => 'boolean',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string|max:50',
            'sort_order' => 'integer|min:0',
        ]);

        // Définir sort_order par défaut si non fourni
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = ResourceType::max('sort_order') + 1;
        }

        // Validation conditionnelle : si requires_file est true, allowed_file_types doit être fourni
        if ($validated['requires_file'] ?? false) {
            if (empty($validated['allowed_file_types'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les types de fichiers autorisés sont requis quand le fichier est obligatoire',
                    'errors' => [
                        'allowed_file_types' => ['Ce champ est requis quand le fichier est obligatoire']
                    ]
                ], 422);
            }

            // Normaliser les extensions de fichiers
            $validated['allowed_file_types'] = array_map(function($type) {
                return strtolower(ltrim($type, '.'));
            }, $validated['allowed_file_types']);
        } else {
            // Si requires_file est false, on vide les types autorisés
            $validated['allowed_file_types'] = null;
        }

        $resourceType = ResourceType::create($validated);

        return response()->json([
            'success' => true,
            'data' => $resourceType,
            'message' => 'Type de ressource créé avec succès'
        ], 201);
    }

    /**
     * Display the specified resource type.
     */
    public function show(ResourceType $resourceType)
    {
        $resourceType->load('resources:id,title,resource_type_id,created_at');

        return response()->json([
            'success' => true,
            'data' => $resourceType,
            'message' => 'Type de ressource récupéré avec succès'
        ]);
    }

    /**
     * Update the specified resource type.
     */
    public function update(Request $request, ResourceType $resourceType)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('resource_types', 'name')->ignore($resourceType->id)
            ],
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'is_active' => 'boolean',
            'requires_file' => 'boolean',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string|max:50',
            'sort_order' => 'integer|min:0',
        ]);

        // Validation conditionnelle
        if ($validated['requires_file'] ?? false) {
            if (empty($validated['allowed_file_types'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les types de fichiers autorisés sont requis quand le fichier est obligatoire',
                    'errors' => [
                        'allowed_file_types' => ['Ce champ est requis quand le fichier est obligatoire']
                    ]
                ], 422);
            }

            // Normaliser les extensions de fichiers
            $validated['allowed_file_types'] = array_map(function($type) {
                return strtolower(ltrim($type, '.'));
            }, $validated['allowed_file_types']);
        } else {
            $validated['allowed_file_types'] = null;
        }

        $resourceType->update($validated);

        return response()->json([
            'success' => true,
            'data' => $resourceType->fresh(),
            'message' => 'Type de ressource mis à jour avec succès'
        ]);
    }

    /**
     * Remove the specified resource type.
     */
    public function destroy(ResourceType $resourceType)
    {
        // Vérifier s'il y a des ressources associées
        if ($resourceType->resources()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un type de ressource qui contient des ressources'
            ], 422);
        }

        $resourceType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type de ressource supprimé avec succès'
        ]);
    }

    /**
     * Get active resource types for dropdown/select.
     */
    public function getActiveTypes()
    {
        $resourceTypes = ResourceType::active()
            ->ordered()
            ->get(['id', 'name', 'icon', 'color', 'requires_file', 'allowed_file_types']);

        return response()->json([
            'success' => true,
            'data' => $resourceTypes,
            'message' => 'Types de ressources actifs récupérés avec succès'
        ]);
    }

    /**
     * Update sort order for multiple resource types.
     */
    public function updateSortOrder(Request $request)
    {
        $validated = $request->validate([
            'types' => 'required|array',
            'types.*.id' => 'required|exists:resource_types,id',
            'types.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['types'] as $typeData) {
            ResourceType::where('id', $typeData['id'])
                ->update(['sort_order' => $typeData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre des types de ressources mis à jour avec succès'
        ]);
    }

    /**
     * Toggle the active status of a resource type.
     */
    public function toggleStatus(ResourceType $resourceType)
    {
        $resourceType->update([
            'is_active' => !$resourceType->is_active
        ]);

        $status = $resourceType->is_active ? 'activé' : 'désactivé';

        return response()->json([
            'success' => true,
            'data' => $resourceType->fresh(),
            'message' => "Type de ressource {$status} avec succès"
        ]);
    }

    /**
     * Validate file type against resource type.
     */
    public function validateFileType(Request $request, ResourceType $resourceType)
    {
        $validated = $request->validate([
            'file_type' => 'required|string',
        ]);

        $fileType = $validated['file_type'];
        $isAllowed = $resourceType->isFileTypeAllowed($fileType);

        return response()->json([
            'success' => true,
            'data' => [
                'is_allowed' => $isAllowed,
                'file_type' => $fileType,
                'resource_type' => $resourceType->name,
                'allowed_types' => $resourceType->allowed_file_types,
            ],
            'message' => $isAllowed
                ? 'Type de fichier autorisé'
                : 'Type de fichier non autorisé pour ce type de ressource'
        ]);
    }

    /**
     * Get resource types statistics.
     */
    public function getStats()
    {
        $stats = [
            'total' => ResourceType::count(),
            'active' => ResourceType::active()->count(),
            'inactive' => ResourceType::where('is_active', false)->count(),
            'requiring_files' => ResourceType::where('requires_file', true)->count(),
            'not_requiring_files' => ResourceType::where('requires_file', false)->count(),
            'with_resources' => ResourceType::has('resources')->count(),
            'without_resources' => ResourceType::doesntHave('resources')->count(),
        ];

        // Types les plus utilisés
        $mostUsedTypes = ResourceType::withCount('resources')
            ->orderBy('resources_count', 'desc')
            ->take(5)
            ->get(['id', 'name', 'color', 'resources_count']);

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $stats,
                'most_used' => $mostUsedTypes,
            ],
            'message' => 'Statistiques des types de ressources récupérées avec succès'
        ]);
    }

    /**
     * Get common file types for suggestions.
     */
    public function getCommonFileTypes()
    {
        $commonTypes = [
            'documents' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'],
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'],
            'videos' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'],
            'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'wma'],
            'spreadsheets' => ['xls', 'xlsx', 'csv', 'ods'],
            'presentations' => ['ppt', 'pptx', 'odp'],
            'archives' => ['zip', 'rar', '7z', 'tar', 'gz'],
            'code' => ['html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'json', 'xml'],
        ];

        return response()->json([
            'success' => true,
            'data' => $commonTypes,
            'message' => 'Types de fichiers communs récupérés avec succès'
        ]);
    }
}
