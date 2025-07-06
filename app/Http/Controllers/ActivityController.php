<?php

namespace App\Http\Controllers;

use App\Models\ResourceActivity;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ActivityController extends Controller
{
    /**
     * Afficher les activités publiques
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = ResourceActivity::with([
            'resource:id,title,category_id',
            'resource.category:id,name,color,icon',
            'creator:id,name'
        ])
            ->where(function($q) use ($user) {
                // Activités publiques ouvertes
                $q->where('is_private', false)
                    ->where('status', 'open');

                // Ou activités où l'utilisateur est invité/participant
                $q->orWhereHas('participants', function($subQ) use ($user) {
                    $subQ->where('user_id', $user->id)
                        ->whereIn('status', ['invited', 'accepted', 'participating']);
                });
            })
            ->orderBy('scheduled_at', 'asc');

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->whereHas('resource', function($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        if ($request->has('upcoming')) {
            $query->where('scheduled_at', '>', now());
        }

        // Pagination
        $activities = $query->paginate(10);

        // Enrichir avec les informations de participation
        foreach ($activities as $activity) {
            $participation = $activity->participants()
                ->where('user_id', $user->id)
                ->first();

            $activity->user_participation = $participation;
            $activity->can_join = $user->canJoinActivity($activity);
        }

        return response()->json([
            'data' => $activities,
            'message' => 'Activities retrieved successfully'
        ]);
    }

    /**
     * Afficher une activité spécifique
     */
    public function show(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier l'accès
        if ($activity->is_private) {
            $hasAccess = $activity->created_by === $user->id ||
                $activity->participants()->where('user_id', $user->id)->exists();

            if (!$hasAccess) {
                return response()->json(['message' => 'Activity not accessible'], 403);
            }
        }

        $activity->load([
            'resource:id,title,description,category_id,resource_type_id',
            'resource.category:id,name,color,icon',
            'resource.resourceType:id,name,icon',
            'creator:id,name',
            'participants.user:id,name',
            'participants' => function($q) {
                $q->whereIn('status', ['accepted', 'participating', 'completed']);
            }
        ]);

        // Informations de participation de l'utilisateur
        $participation = $activity->participants()
            ->where('user_id', $user->id)
            ->first();

        $activity->user_participation = $participation;
        $activity->can_join = $user->canJoinActivity($activity);
        $activity->can_manage = $user->canManageActivity($activity);

        return response()->json([
            'data' => $activity,
            'message' => 'Activity retrieved successfully'
        ]);
    }

    /**
     * Créer une nouvelle activité
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'resource_id' => 'required|exists:resources,id',
            'max_participants' => 'required|integer|min:2|max:50',
            'is_private' => 'boolean',
            'scheduled_at' => 'required|date|after:now',
            'estimated_duration_minutes' => 'nullable|integer|min:15|max:480',
            'instructions' => 'nullable|string|max:2000',
            'activity_data' => 'nullable|array',
        ]);

        // Vérifier l'accès à la ressource
        $resource = Resource::find($validated['resource_id']);
        if (!$user->canView($resource)) {
            return response()->json(['message' => 'Resource not accessible'], 403);
        }

        $validated['created_by'] = $user->id;
        $validated['status'] = 'draft';
        $validated['is_private'] = $validated['is_private'] ?? false;

        // Générer un code d'accès unique
        $validated['access_code'] = strtoupper(Str::random(6));

        $activity = ResourceActivity::create($validated);

        // Ajouter le créateur comme facilitateur
        $activity->participants()->create([
            'user_id' => $user->id,
            'role' => 'facilitator',
            'status' => 'accepted',
            'joined_at' => now(),
        ]);

        $activity->load([
            'resource:id,title,category_id',
            'resource.category:id,name,color',
            'creator:id,name'
        ]);

        return response()->json([
            'data' => $activity,
            'message' => 'Activity created successfully'
        ], 201);
    }

    /**
     * Mettre à jour une activité
     */
    public function update(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que l'activité peut être modifiée
        if (in_array($activity->status, ['in_progress', 'completed', 'cancelled'])) {
            return response()->json(['message' => 'Cannot modify activity in this status'], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_participants' => 'sometimes|required|integer|min:2|max:50',
            'is_private' => 'boolean',
            'scheduled_at' => 'sometimes|required|date|after:now',
            'estimated_duration_minutes' => 'nullable|integer|min:15|max:480',
            'instructions' => 'nullable|string|max:2000',
            'activity_data' => 'nullable|array',
        ]);

        // Vérifier que le nouveau nombre max de participants n'est pas inférieur au nombre actuel
        if (isset($validated['max_participants']) &&
            $validated['max_participants'] < $activity->participant_count) {
            return response()->json([
                'message' => 'Cannot reduce max participants below current participant count',
                'current_participants' => $activity->participant_count
            ], 422);
        }

        $activity->update($validated);

        $activity->load([
            'resource:id,title,category_id',
            'resource.category:id,name,color',
            'creator:id,name'
        ]);

        return response()->json([
            'data' => $activity,
            'message' => 'Activity updated successfully'
        ]);
    }

    /**
     * Supprimer une activité
     */
    public function destroy(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que l'activité peut être supprimée
        if (in_array($activity->status, ['in_progress', 'completed'])) {
            return response()->json(['message' => 'Cannot delete activity in this status'], 422);
        }

        $activity->delete();

        return response()->json([
            'message' => 'Activity deleted successfully'
        ]);
    }

    /**
     * Rejoindre une activité
     */
    public function join(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier si l'utilisateur peut rejoindre
        if (!$user->canJoinActivity($activity)) {
            return response()->json(['message' => 'Cannot join this activity'], 403);
        }

        // Vérifier si déjà participant
        $existingParticipation = $activity->participants()
            ->where('user_id', $user->id)
            ->first();

        if ($existingParticipation) {
            if ($existingParticipation->status === 'invited') {
                // Accepter l'invitation
                $existingParticipation->accept();
                $message = 'Invitation accepted successfully';
            } elseif ($existingParticipation->status === 'accepted') {
                return response()->json(['message' => 'Already joined this activity'], 422);
            } else {
                return response()->json(['message' => 'Cannot join - check participation status'], 422);
            }
        } else {
            // Rejoindre directement (activité publique)
            $activity->participants()->create([
                'user_id' => $user->id,
                'role' => 'participant',
                'status' => 'accepted',
                'joined_at' => now(),
            ]);
            $message = 'Activity joined successfully';
        }

        $activity->updateParticipantCount();

        return response()->json([
            'message' => $message,
            'participant_count' => $activity->fresh()->participant_count
        ]);
    }

    /**
     * Quitter une activité
     */
    public function leave(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        $participation = $activity->participants()
            ->where('user_id', $user->id)
            ->first();

        if (!$participation) {
            return response()->json(['message' => 'Not participating in this activity'], 404);
        }

        // Le créateur ne peut pas quitter sa propre activité
        if ($activity->created_by === $user->id) {
            return response()->json(['message' => 'Activity creator cannot leave'], 422);
        }

        $participation->leave();
        $activity->updateParticipantCount();

        return response()->json([
            'message' => 'Left activity successfully',
            'participant_count' => $activity->fresh()->participant_count
        ]);
    }

    /**
     * Démarrer une activité
     */
    public function start(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier le statut
        if ($activity->status !== 'open') {
            return response()->json(['message' => 'Activity must be open to start'], 422);
        }

        $activity->start();

        return response()->json([
            'data' => $activity,
            'message' => 'Activity started successfully'
        ]);
    }

    /**
     * Terminer une activité
     */
    public function complete(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier le statut
        if ($activity->status !== 'in_progress') {
            return response()->json(['message' => 'Activity must be in progress to complete'], 422);
        }

        $validated = $request->validate([
            'results' => 'nullable|array',
        ]);

        $activity->complete($validated['results'] ?? null);

        return response()->json([
            'data' => $activity,
            'message' => 'Activity completed successfully'
        ]);
    }

    /**
     * Annuler une activité
     */
    public function cancel(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier le statut
        if (in_array($activity->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Cannot cancel activity in this status'], 422);
        }

        $activity->cancel();

        return response()->json([
            'data' => $activity,
            'message' => 'Activity cancelled successfully'
        ]);
    }

    /**
     * Mes activités créées
     */
    public function myCreated(): JsonResponse
    {
        $user = auth()->user();

        $activities = $user->createdActivities()
            ->with([
                'resource:id,title,category_id',
                'resource.category:id,name,color,icon'
            ])
            ->withCount('participants')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $activities,
            'message' => 'Created activities retrieved successfully'
        ]);
    }

    /**
     * Mes participations
     */
    public function myParticipating(): JsonResponse
    {
        $user = auth()->user();

        $participations = $user->activityParticipations()
            ->with([
                'activity:id,title,description,status,scheduled_at,started_at,resource_id,created_by',
                'activity.resource:id,title,category_id',
                'activity.resource.category:id,name,color,icon',
                'activity.creator:id,name'
            ])
            ->whereIn('status', ['accepted', 'participating', 'completed'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $participations,
            'message' => 'Participating activities retrieved successfully'
        ]);
    }

    /**
     * Mes invitations
     */
    public function myInvitations(): JsonResponse
    {
        $user = auth()->user();

        $invitations = $user->activityParticipations()
            ->with([
                'activity:id,title,description,status,scheduled_at,resource_id,created_by',
                'activity.resource:id,title,category_id',
                'activity.resource.category:id,name,color,icon',
                'activity.creator:id,name',
                'inviter:id,name'
            ])
            ->where('status', 'invited')
            ->orderBy('invited_at', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $invitations,
            'message' => 'Invitations retrieved successfully'
        ]);
    }

    /**
     * Publier une activité (la rendre ouverte aux inscriptions)
     */
    public function publish(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier le statut
        if ($activity->status !== 'draft') {
            return response()->json(['message' => 'Only draft activities can be published'], 422);
        }

        // Vérifications avant publication
        if (empty($activity->title) || empty($activity->scheduled_at)) {
            return response()->json(['message' => 'Activity must have title and scheduled date'], 422);
        }

        $activity->update(['status' => 'open']);

        return response()->json([
            'data' => $activity,
            'message' => 'Activity published successfully'
        ]);
    }

    /**
     * Générer un nouveau code d'accès
     */
    public function regenerateAccessCode(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $newCode = $activity->generateNewAccessCode();

        return response()->json([
            'access_code' => $newCode,
            'message' => 'Access code regenerated successfully'
        ]);
    }
}
