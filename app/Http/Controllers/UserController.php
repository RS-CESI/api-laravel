<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('role')->get();
        return response()->json($users);
    }

    public function show(User $user)
    {
        $user->load('role');
        return response()->json($user);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8'
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->noContent();
    }

    // ==========================================
    // MÉTHODES PROFILE
    // ==========================================

    /**
     * Afficher le profil de l'utilisateur connecté
     */
    public function profile()
    {
        $user = Auth::user();
        $user->load('role');

        // Récupérer les statistiques personnelles
        $stats = $user->getPersonalStats();

        // Formater la réponse avec toutes les données nécessaires
        $profileData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->name : 'citizen',
                'is_verified' => $user->is_verified,
                'initials' => $user->initials,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'permissions' => [
                    'can_moderate' => $user->canModerate(),
                    'is_admin' => $user->isAdmin(),
                    'is_super_admin' => $user->isSuperAdmin(),
                ],
            ],
            'stats' => $stats
        ];

        return response()->json($profileData);
    }

    /**
     * Mettre à jour le profil de l'utilisateur connecté
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($user->id)
            ],
        ]);

        $user->update($validated);
        $user->load('role');

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user->toApiArray()
        ]);
    }

    /**
     * Changer le mot de passe de l'utilisateur connecté
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        // Vérifier le mot de passe actuel
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.'
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès'
        ]);
    }

    /**
     * Mettre à jour les préférences de notification
     */
    public function updateNotificationPreferences(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'email_notifications' => 'boolean',
            'resource_notifications' => 'boolean',
            'recommendation_notifications' => 'boolean',
        ]);

        // Si vous avez une table pour les préférences de notification
        // ou des colonnes dans la table users, adaptez selon votre structure

        // Exemple si vous ajoutez des colonnes à la table users
        $user->update([
            'email_notifications' => $validated['email_notifications'] ?? $user->email_notifications,
            'resource_notifications' => $validated['resource_notifications'] ?? $user->resource_notifications,
            'recommendation_notifications' => $validated['recommendation_notifications'] ?? $user->recommendation_notifications,
        ]);

        return response()->json([
            'message' => 'Préférences mises à jour avec succès',
            'preferences' => [
                'email_notifications' => $user->email_notifications,
                'resource_notifications' => $user->resource_notifications,
                'recommendation_notifications' => $user->recommendation_notifications,
            ]
        ]);
    }

    /**
     * Supprimer le compte de l'utilisateur connecté
     */
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'password' => 'required',
        ]);

        // Vérifier le mot de passe avant suppression
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Mot de passe incorrect.'
            ], 422);
        }

        // Supprimer le compte
        $user->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès'
        ]);
    }

    /**
     * Télécharger les données personnelles (RGPD)
     */
    public function downloadPersonalData()
    {
        $user = Auth::user();
        $user->load(['role', 'resources', 'favorites', 'progressions', 'comments']);

        $data = [
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ],
            'role' => $user->role ? $user->role->name : null,
            'resources_created' => $user->resources->count(),
            'favorites_count' => $user->favorites->count(),
            'progressions_count' => $user->progressions->count(),
            'comments_count' => $user->comments->count(),
            'personal_stats' => $user->getPersonalStats(),
        ];

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="personal-data.json"');
    }
}
