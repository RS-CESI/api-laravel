<?php

namespace App\Http\Controllers;

use App\Models\ResourceActivity;
use App\Models\ActivityParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityParticipantController extends Controller
{
    /**
     * Afficher les participants d'une activité
     */
    public function index(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier l'accès à l'activité
        if ($activity->is_private) {
            $hasAccess = $activity->created_by === $user->id ||
                $activity->participants()->where('user_id', $user->id)->exists();

            if (!$hasAccess) {
                return response()->json(['message' => 'Activity not accessible'], 403);
            }
        }

        $participants = $activity->participants()
            ->with('user:id,name,email')
            ->whereIn('status', ['invited', 'accepted', 'participating', 'completed'])
            ->orderBy('role', 'desc') // Facilitateurs en premier
            ->orderBy('joined_at', 'asc')
            ->get();

        // Grouper par statut pour une meilleure présentation
        $groupedParticipants = $participants->groupBy('status');

        return response()->json([
            'data' => [
                'participants' => $participants,
                'grouped' => $groupedParticipants,
                'stats' => [
                    'total' => $participants->count(),
                    'accepted' => $participants->where('status', 'accepted')->count(),
                    'participating' => $participants->where('status', 'participating')->count(),
                    'completed' => $participants->where('status', 'completed')->count(),
                    'facilitators' => $participants->where('role', 'facilitator')->count(),
                ]
            ],
            'message' => 'Participants retrieved successfully'
        ]);
    }

    /**
     * Inviter un utilisateur à une activité
     */
    public function invite(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions (créateur ou facilitateur)
        if (!$user->canManageActivity($activity) &&
            !$activity->participants()->where('user_id', $user->id)->where('role', 'facilitator')->exists()) {
            return response()->json(['message' => 'Forbidden - Cannot invite participants'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'email' => 'sometimes|email|exists:users,email',
            'user_ids' => 'sometimes|array|max:10',
            'user_ids.*' => 'exists:users,id',
            'invitation_message' => 'nullable|string|max:500',
            'role' => 'sometimes|in:participant,facilitator,observer',
        ]);

        // Déterminer les utilisateurs à inviter
        $usersToInvite = [];

        if (isset($validated['user_id'])) {
            $usersToInvite[] = $validated['user_id'];
        } elseif (isset($validated['email'])) {
            $targetUser = User::where('email', $validated['email'])->first();
            $usersToInvite[] = $targetUser->id;
        } elseif (isset($validated['user_ids'])) {
            $usersToInvite = $validated['user_ids'];
        } else {
            return response()->json(['message' => 'Must specify user_id, email, or user_ids'], 422);
        }

        $role = $validated['role'] ?? 'participant';
        $message = $validated['invitation_message'] ?? null;

        $invited = [];
        $alreadyParticipating = [];
        $errors = [];

        foreach ($usersToInvite as $userId) {
            // Vérifier si déjà participant
            $existingParticipation = $activity->participants()
                ->where('user_id', $userId)
                ->first();

            if ($existingParticipation) {
                $alreadyParticipating[] = $userId;
                continue;
            }

            // Vérifier la limite de participants
            if ($activity->participant_count >= $activity->max_participants) {
                $errors[] = "Activity is full (max {$activity->max_participants} participants)";
                break;
            }

            try {
                $participant = $activity->invite(
                    User::find($userId),
                    $user,
                    $message
                );

                $participant->update(['role' => $role]);
                $invited[] = $userId;
            } catch (\Exception $e) {
                $errors[] = "Failed to invite user {$userId}: " . $e->getMessage();
            }
        }

        $activity->updateParticipantCount();

        return response()->json([
            'data' => [
                'invited_users' => $invited,
                'already_participating' => $alreadyParticipating,
                'errors' => $errors,
                'invitation_summary' => [
                    'invited_count' => count($invited),
                    'already_participating_count' => count($alreadyParticipating),
                    'error_count' => count($errors),
                ]
            ],
            'message' => count($invited) > 0
                ? 'Invitations sent successfully'
                : 'No new invitations sent'
        ]);
    }

    /**
     * Accepter une invitation
     */
    public function accept(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        $participation = $activity->participants()
            ->where('user_id', $user->id)
            ->where('status', 'invited')
            ->first();

        if (!$participation) {
            return response()->json(['message' => 'No pending invitation found'], 404);
        }

        // Vérifier la limite de participants
        if ($activity->participant_count >= $activity->max_participants) {
            return response()->json([
                'message' => 'Activity is full',
                'max_participants' => $activity->max_participants
            ], 422);
        }

        $participation->accept();
        $activity->updateParticipantCount();

        $participation->load('user:id,name');

        return response()->json([
            'data' => $participation,
            'message' => 'Invitation accepted successfully'
        ]);
    }

    /**
     * Refuser une invitation
     */
    public function decline(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        $participation = $activity->participants()
            ->where('user_id', $user->id)
            ->where('status', 'invited')
            ->first();

        if (!$participation) {
            return response()->json(['message' => 'No pending invitation found'], 404);
        }

        $participation->decline();

        return response()->json([
            'message' => 'Invitation declined successfully'
        ]);
    }

    /**
     * Mettre à jour un participant (rôle, statut)
     */
    public function update(Request $request, ResourceActivity $activity, ActivityParticipant $participant): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que le participant appartient à cette activité
        if ($participant->resource_activity_id !== $activity->id) {
            return response()->json(['message' => 'Participant not found in this activity'], 404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|in:participant,facilitator,observer',
            'status' => 'sometimes|in:invited,accepted,declined,participating,completed,left',
            'score' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'participation_data' => 'nullable|array',
        ]);

        // Protéger le créateur de l'activité
        if ($participant->user_id === $activity->created_by) {
            if (isset($validated['role']) && $validated['role'] !== 'facilitator') {
                return response()->json(['message' => 'Activity creator must remain facilitator'], 422);
            }
            if (isset($validated['status']) && !in_array($validated['status'], ['accepted', 'participating', 'completed'])) {
                return response()->json(['message' => 'Activity creator cannot be removed'], 422);
            }
        }

        $participant->update($validated);
        $activity->updateParticipantCount();

        $participant->load('user:id,name');

        return response()->json([
            'data' => $participant,
            'message' => 'Participant updated successfully'
        ]);
    }

    /**
     * Retirer un participant
     */
    public function destroy(ResourceActivity $activity, ActivityParticipant $participant): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que le participant appartient à cette activité
        if ($participant->resource_activity_id !== $activity->id) {
            return response()->json(['message' => 'Participant not found in this activity'], 404);
        }

        // Protéger le créateur de l'activité
        if ($participant->user_id === $activity->created_by) {
            return response()->json(['message' => 'Cannot remove activity creator'], 422);
        }

        $participant->delete();
        $activity->updateParticipantCount();

        return response()->json([
            'message' => 'Participant removed successfully'
        ]);
    }

    /**
     * Promouvoir un participant comme facilitateur
     */
    public function promote(ResourceActivity $activity, ActivityParticipant $participant): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que le participant appartient à cette activité
        if ($participant->resource_activity_id !== $activity->id) {
            return response()->json(['message' => 'Participant not found in this activity'], 404);
        }

        // Vérifier que le participant est actif
        if (!in_array($participant->status, ['accepted', 'participating'])) {
            return response()->json(['message' => 'Can only promote active participants'], 422);
        }

        $participant->update(['role' => 'facilitator']);

        $participant->load('user:id,name');

        return response()->json([
            'data' => $participant,
            'message' => 'Participant promoted to facilitator successfully'
        ]);
    }

    /**
     * Rétrograder un facilitateur en participant
     */
    public function demote(ResourceActivity $activity, ActivityParticipant $participant): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que le participant appartient à cette activité
        if ($participant->resource_activity_id !== $activity->id) {
            return response()->json(['message' => 'Participant not found in this activity'], 404);
        }

        // Protéger le créateur de l'activité
        if ($participant->user_id === $activity->created_by) {
            return response()->json(['message' => 'Cannot demote activity creator'], 422);
        }

        $participant->update(['role' => 'participant']);

        $participant->load('user:id,name');

        return response()->json([
            'data' => $participant,
            'message' => 'Facilitator demoted to participant successfully'
        ]);
    }

    /**
     * Marquer la participation comme terminée
     */
    public function markCompleted(Request $request, ResourceActivity $activity, ActivityParticipant $participant): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions (gestionnaire ou le participant lui-même)
        if (!$user->canManageActivity($activity) && $participant->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Vérifier que le participant appartient à cette activité
        if ($participant->resource_activity_id !== $activity->id) {
            return response()->json(['message' => 'Participant not found in this activity'], 404);
        }

        $validated = $request->validate([
            'score' => 'nullable|integer|min:0|max:100',
            'participation_data' => 'nullable|array',
            'activity_rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:500',
        ]);

        $participant->complete(
            $validated['score'] ?? null,
            $validated['participation_data'] ?? null
        );

        // Ajouter l'évaluation si fournie
        if (isset($validated['activity_rating']) || isset($validated['feedback'])) {
            $participant->rate(
                $validated['activity_rating'] ?? null,
                $validated['feedback'] ?? null
            );
        }

        $participant->load('user:id,name');

        return response()->json([
            'data' => $participant,
            'message' => 'Participation marked as completed successfully'
        ]);
    }

    /**
     * Ajouter du temps de participation
     */
    public function addTime(Request $request, ResourceActivity $activity, ActivityParticipant $participant): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions (gestionnaire ou le participant lui-même)
        if (!$user->canManageActivity($activity) && $participant->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:480', // Max 8h par session
        ]);

        $participant->addTimeSpent($validated['minutes']);

        return response()->json([
            'data' => [
                'total_time_spent' => $participant->fresh()->time_spent_minutes,
                'formatted_time' => $participant->fresh()->formatted_time_spent,
            ],
            'message' => 'Time added successfully'
        ]);
    }

    /**
     * Obtenir les statistiques des participants
     */
    public function statistics(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $stats = [
            'total_participants' => $activity->participants()->count(),
            'by_status' => $activity->participants()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
            'by_role' => $activity->participants()
                ->selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->get(),
            'completion_rate' => $activity->participants()->completed()->count() /
                max($activity->participants()->count(), 1) * 100,
            'average_score' => $activity->participants()
                ->whereNotNull('score')
                ->avg('score'),
            'average_rating' => $activity->participants()
                ->whereNotNull('activity_rating')
                ->avg('activity_rating'),
            'total_time_spent' => $activity->participants()->sum('time_spent_minutes'),
            'top_performers' => $activity->participants()
                ->whereNotNull('score')
                ->with('user:id,name')
                ->orderBy('score', 'desc')
                ->limit(5)
                ->get(['user_id', 'score']),
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Participant statistics retrieved successfully'
        ]);
    }

    /**
     * Exporter la liste des participants
     */
    public function export(ResourceActivity $activity): JsonResponse
    {
        $user = auth()->user();

        // Vérifier les permissions
        if (!$user->canManageActivity($activity)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $participants = $activity->participants()
            ->with('user:id,name,email')
            ->get()
            ->map(function($participant) {
                return [
                    'name' => $participant->user->name,
                    'email' => $participant->user->email,
                    'role' => $participant->role,
                    'status' => $participant->status,
                    'invited_at' => $participant->invited_at?->format('Y-m-d H:i:s'),
                    'joined_at' => $participant->joined_at?->format('Y-m-d H:i:s'),
                    'time_spent' => $participant->formatted_time_spent,
                    'score' => $participant->score,
                    'rating' => $participant->activity_rating,
                ];
            });

        return response()->json([
            'data' => $participants,
            'meta' => [
                'activity_title' => $activity->title,
                'export_date' => now()->format('Y-m-d H:i:s'),
                'total_participants' => $participants->count(),
            ],
            'message' => 'Participant list exported successfully'
        ]);
    }
}
