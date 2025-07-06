<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    /**
     * Constructeur - Vérifier les permissions admin
     */
    public function __construct()
    {
        $this->middleware('role:administrator,super-administrator');
    }

    /**
     * Afficher tous les utilisateurs (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role:id,name');

        // Filtres
        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->has('role_name')) {
            $query->whereHas('role', function($q) use ($request) {
                $q->where('name', $request->role_name);
            });
        }

        if ($request->has('email_verified')) {
            if ($request->email_verified === 'true') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['created_at', 'name', 'email', 'email_verified_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $users = $query->paginate($request->get('per_page', 20));

        // Enrichir avec les statistiques utilisateur
        foreach ($users as $user) {
            $user->stats = $user->getPersonalStats();
        }

        // Statistiques générales
        $stats = [
            'total_users' => User::count(),
            'by_role' => Role::withCount('users')->get(['id', 'name']),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            'active_users_this_month' => User::whereHas('progressions', function($query) {
                $query->where('last_accessed_at', '>=', now()->subMonth());
            })->count(),
        ];

        return response()->json([
            'data' => $users,
            'stats' => $stats,
            'message' => 'Users retrieved successfully'
        ]);
    }

    /**
     * Afficher un utilisateur spécifique (admin)
     */
    public function show(User $user): JsonResponse
    {
        $user->load([
            'role',
            'resources' => function($query) {
                $query->with('category:id,name,color')->latest()->limit(10);
            },
            'favoriteResources' => function($query) {
                $query->with('category:id,name,color')->latest()->limit(5);
            },
            'progressions' => function($query) {
                $query->with('resource:id,title,category_id')
                    ->with('resource.category:id,name,color')
                    ->latest()
                    ->limit(10);
            },
            'activityParticipations' => function($query) {
                $query->with('activity:id,title,status,created_at')
                    ->latest()
                    ->limit(5);
            }
        ]);

        // Statistiques détaillées
        $user->detailed_stats = [
            // Activité générale
            'account_age_days' => $user->created_at->diffInDays(now()),
            'last_login' => $user->progressions()->latest('last_accessed_at')->value('last_accessed_at'),
            'is_active' => $user->progressions()->where('last_accessed_at', '>=', now()->subDays(30))->exists(),

            // Contenu créé
            'total_resources' => $user->resources()->count(),
            'published_resources' => $user->resources()->published()->count(),
            'total_views_received' => $user->resources()->sum('view_count'),
            'total_favorites_received' => $user->resources()->sum('favorite_count'),

            // Engagement
            'total_progressions' => $user->progressions()->count(),
            'completed_progressions' => $user->progressions()->completed()->count(),
            'total_time_spent' => $user->progressions()->sum('time_spent_minutes'),
            'favorite_resources' => $user->favorites()->count(),

            // Activités collaboratives
            'activities_created' => $user->createdActivities()->count(),
            'activities_participated' => $user->activityParticipations()->whereIn('status', ['completed', 'participating'])->count(),

            // Commentaires
            'comments_posted' => $user->comments()->count(),
            'approved_comments' => $user->comments()->approved()->count(),

            // Modération (si applicable)
            'resources_validated' => $user->canModerate() ? $user->validatedResources()->count() : 0,
            'comments_moderated' => $user->canModerate() ? $user->moderatedComments()->count() : 0,
        ];

        return response()->json([
            'data' => $user,
            'message' => 'User details retrieved successfully'
        ]);
    }

    /**
     * Créer un utilisateur (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'email_verified' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // Admin peut créer des comptes déjà vérifiés
        if ($validated['email_verified'] ?? false) {
            $validated['email_verified_at'] = now();
        }

        unset($validated['email_verified']);

        $user = User::create($validated);
        $user->load('role');

        // Log de la création
        Log::info('User created by admin', [
            'admin_id' => auth()->user()->id,
            'admin_name' => auth()->user()->name,
            'created_user_id' => $user->id,
            'created_user_email' => $user->email,
            'assigned_role' => $user->role->name,
        ]);

        return response()->json([
            'data' => $user,
            'message' => 'User created successfully'
        ], 201);
    }

    /**
     * Mettre à jour un utilisateur (admin)
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = auth()->user();

        // Super-admin peut tout modifier, admin ne peut pas modifier les super-admins
        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot modify super administrator'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'role_id' => 'sometimes|required|exists:roles,id',
            'email_verified' => 'sometimes|boolean',
        ]);

        // Vérifier les permissions pour le changement de rôle
        if (isset($validated['role_id'])) {
            $newRole = Role::find($validated['role_id']);

            // Seul un super-admin peut créer/modifier d'autres super-admins
            if ($newRole->name === 'super-administrator' && !$currentUser->isSuperAdmin()) {
                return response()->json(['message' => 'Cannot assign super administrator role'], 403);
            }

            // Admin ne peut pas assigner de rôle admin/modérateur sans être super-admin
            if (in_array($newRole->name, ['administrator', 'moderator']) && !$currentUser->isSuperAdmin()) {
                return response()->json(['message' => 'Cannot assign administrative roles'], 403);
            }
        }

        $oldData = $user->only(['name', 'email', 'role_id']);

        // Mettre à jour le mot de passe si fourni
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Gérer la vérification email
        if (isset($validated['email_verified'])) {
            if ($validated['email_verified'] && !$user->email_verified_at) {
                $validated['email_verified_at'] = now();
            } elseif (!$validated['email_verified']) {
                $validated['email_verified_at'] = null;
            }
            unset($validated['email_verified']);
        }

        $user->update($validated);
        $user->load('role');

        // Log des modifications importantes
        if (isset($validated['role_id']) && $oldData['role_id'] !== $validated['role_id']) {
            Log::warning('User role changed by admin', [
                'admin_id' => $currentUser->id,
                'admin_name' => $currentUser->name,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_role_id' => $oldData['role_id'],
                'new_role_id' => $validated['role_id'],
                'new_role_name' => $user->role->name,
            ]);
        }

        return response()->json([
            'data' => $user,
            'message' => 'User updated successfully'
        ]);
    }

    /**
     * Supprimer un utilisateur (admin)
     */
    public function destroy(User $user): JsonResponse
    {
        $currentUser = auth()->user();

        // Protections
        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot delete super administrator'], 403);
        }

        // Vérifier s'il y a du contenu lié
        $resourceCount = $user->resources()->count();
        $activityCount = $user->createdActivities()->count();

        if ($resourceCount > 0 || $activityCount > 0) {
            return response()->json([
                'message' => 'Cannot delete user with associated content',
                'resources_count' => $resourceCount,
                'activities_count' => $activityCount,
                'suggestion' => 'Consider deactivating the user instead'
            ], 422);
        }

        // Sauvegarder les informations pour le log
        $userInfo = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->name,
            'created_at' => $user->created_at,
        ];

        $user->delete();

        // Log de la suppression
        Log::critical('User deleted by admin', [
            'admin_id' => $currentUser->id,
            'admin_name' => $currentUser->name,
            'deleted_user' => $userInfo,
        ]);

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Activer un utilisateur
     */
    public function activate(User $user): JsonResponse
    {
        // Pour une future fonctionnalité de suspension
        // Ici on pourrait mettre à jour un champ 'is_active' ou 'suspended_at'

        Log::info('User activated by admin', [
            'admin_id' => auth()->user()->id,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        return response()->json([
            'data' => $user,
            'message' => 'User activated successfully'
        ]);
    }

    /**
     * Désactiver un utilisateur
     */
    public function deactivate(User $user): JsonResponse
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot deactivate your own account'], 422);
        }

        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot deactivate super administrator'], 403);
        }

        // Pour une future fonctionnalité de suspension
        // $user->update(['suspended_at' => now()]);

        Log::warning('User deactivated by admin', [
            'admin_id' => $currentUser->id,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        return response()->json([
            'data' => $user,
            'message' => 'User deactivated successfully'
        ]);
    }

    /**
     * Changer le rôle d'un utilisateur
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $currentUser = auth()->user();

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $newRole = Role::find($validated['role_id']);
        $oldRole = $user->role;

        // Vérifications de sécurité
        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot modify super administrator role'], 403);
        }

        if ($newRole->name === 'super-administrator' && !$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot assign super administrator role'], 403);
        }

        if (in_array($newRole->name, ['administrator', 'moderator']) && !$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot assign administrative roles'], 403);
        }

        if ($user->id === $currentUser->id && $newRole->name === 'citizen') {
            return response()->json(['message' => 'Cannot demote your own admin privileges'], 422);
        }

        $user->update(['role_id' => $validated['role_id']]);
        $user->load('role');

        Log::warning('User role updated by admin', [
            'admin_id' => $currentUser->id,
            'admin_name' => $currentUser->name,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'old_role' => $oldRole->name,
            'new_role' => $newRole->name,
        ]);

        return response()->json([
            'data' => $user,
            'message' => "User role updated to {$newRole->name} successfully"
        ]);
    }

    /**
     * Créer un modérateur (Super-admin uniquement)
     */
    public function createModerator(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Super administrator access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $moderatorRole = Role::where('name', 'moderator')->first();
        if (!$moderatorRole) {
            return response()->json(['message' => 'Moderator role not found'], 500);
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['role_id'] = $moderatorRole->id;
        $validated['email_verified_at'] = now();

        $user = User::create($validated);
        $user->load('role');

        Log::info('Moderator created by super-admin', [
            'super_admin_id' => $currentUser->id,
            'created_user_id' => $user->id,
            'created_user_email' => $user->email,
        ]);

        return response()->json([
            'data' => $user,
            'message' => 'Moderator created successfully'
        ], 201);
    }

    /**
     * Créer un administrateur (Super-admin uniquement)
     */
    public function createAdministrator(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Super administrator access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $adminRole = Role::where('name', 'administrator')->first();
        if (!$adminRole) {
            return response()->json(['message' => 'Administrator role not found'], 500);
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['role_id'] = $adminRole->id;
        $validated['email_verified_at'] = now();

        $user = User::create($validated);
        $user->load('role');

        Log::warning('Administrator created by super-admin', [
            'super_admin_id' => $currentUser->id,
            'created_user_id' => $user->id,
            'created_user_email' => $user->email,
        ]);

        return response()->json([
            'data' => $user,
            'message' => 'Administrator created successfully'
        ], 201);
    }

    /**
     * Statistiques avancées des utilisateurs
     */
    public function userStatistics(): JsonResponse
    {
        $stats = [
            // Répartition par rôles
            'by_role' => Role::withCount('users')
                ->get(['id', 'name'])
                ->map(function($role) {
                    return [
                        'role' => $role->name,
                        'count' => $role->users_count,
                        'percentage' => User::count() > 0 ? round(($role->users_count / User::count()) * 100, 1) : 0
                    ];
                }),

            // Activité utilisateur
            'activity' => [
                'total_users' => User::count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'active_last_week' => User::whereHas('progressions', function($query) {
                    $query->where('last_accessed_at', '>=', now()->subWeek());
                })->count(),
                'active_last_month' => User::whereHas('progressions', function($query) {
                    $query->where('last_accessed_at', '>=', now()->subMonth());
                })->count(),
                'never_active' => User::doesntHave('progressions')->count(),
            ],

            // Croissance
            'growth' => [
                'new_today' => User::whereDate('created_at', today())->count(),
                'new_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                'new_this_month' => User::where('created_at', '>=', now()->subMonth())->count(),
                'new_this_year' => User::where('created_at', '>=', now()->subYear())->count(),
            ],

            // Engagement
            'engagement' => [
                'users_with_resources' => User::has('resources')->count(),
                'users_with_favorites' => User::has('favorites')->count(),
                'users_with_progressions' => User::has('progressions')->count(),
                'users_with_comments' => User::has('comments')->count(),
                'users_in_activities' => User::has('activityParticipations')->count(),
            ],

            // Top utilisateurs
            'top_creators' => User::withCount('resources')
                ->having('resources_count', '>', 0)
                ->orderBy('resources_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'email'])
                ->map(function($user) {
                    return [
                        'name' => $user->name,
                        'email' => $user->email,
                        'resources_count' => $user->resources_count,
                    ];
                }),

            // Utilisateurs les plus actifs
            'most_active' => User::withCount('progressions')
                ->having('progressions_count', '>', 0)
                ->orderBy('progressions_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'email'])
                ->map(function($user) {
                    return [
                        'name' => $user->name,
                        'email' => $user->email,
                        'progressions_count' => $user->progressions_count,
                    ];
                }),
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'User statistics retrieved successfully'
        ]);
    }

    /**
     * Logs système (Super-admin uniquement)
     */
    public function systemLogs(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Super administrator access required'], 403);
        }

        // Ici vous pourriez implémenter la lecture des logs Laravel
        // Pour l'exemple, on retourne une structure basique

        return response()->json([
            'data' => [
                'message' => 'System logs would be displayed here',
                'note' => 'Implement log reading functionality based on your logging setup'
            ],
            'message' => 'System logs access granted'
        ]);
    }

    /**
     * Audit trail (Super-admin uniquement)
     */
    public function auditTrail(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Super administrator access required'], 403);
        }

        // Ici vous pourriez implémenter un système d'audit trail
        // Pour l'exemple, on retourne les actions récentes basées sur les logs

        return response()->json([
            'data' => [
                'message' => 'Audit trail would be displayed here',
                'note' => 'Implement audit trail functionality for tracking all admin actions'
            ],
            'message' => 'Audit trail access granted'
        ]);
    }

    /**
     * Configuration système (Super-admin uniquement)
     */
    public function systemConfig(): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Super administrator access required'], 403);
        }

        $config = [
            'app_name' => config('app.name'),
            'app_version' => '1.0.0', // À adapter selon votre système de versioning
            'max_file_size' => config('filesystems.max_file_size', '10MB'),
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'mp4', 'mp3', 'jpg', 'png'],
            'registration_enabled' => true,
            'moderation_required' => true,
            'email_verification_required' => true,
        ];

        return response()->json([
            'data' => $config,
            'message' => 'System configuration retrieved successfully'
        ]);
    }

    /**
     * Mettre à jour la configuration système (Super-admin uniquement)
     */
    public function updateSystemConfig(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['message' => 'Super administrator access required'], 403);
        }

        $validated = $request->validate([
            'registration_enabled' => 'sometimes|boolean',
            'moderation_required' => 'sometimes|boolean',
            'email_verification_required' => 'sometimes|boolean',
            'max_file_size' => 'sometimes|string',
        ]);

        // Ici vous pourriez sauvegarder ces configurations
        // dans une table de configuration ou dans des variables d'environnement

        Log::info('System configuration updated by super-admin', [
            'super_admin_id' => $currentUser->id,
            'changes' => $validated,
        ]);

        return response()->json([
            'data' => $validated,
            'message' => 'System configuration updated successfully'
        ]);
    }
}
