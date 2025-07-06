<?php

namespace App\Http\Controllers;

use App\Models\ActivityMessage;
use App\Models\ResourceActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityMessageController extends Controller
{
    /**
     * Display a listing of the messages for an activity.
     */
    public function index(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur a accès à cette activité
        if (!$activity->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $query = ActivityMessage::forActivity($activity->id)
            ->public()
            ->parentMessages()
            ->with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->withCount('replies');

        // Filtres
        if ($request->has('pinned') && $request->pinned) {
            $query->pinned();
        }

        if ($request->has('type') && in_array($request->type, ['text', 'system', 'announcement'])) {
            $query->where('type', $request->type);
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $messages = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $messages,
            'message' => 'Messages récupérés avec succès'
        ]);
    }

    /**
     * Store a newly created message.
     */
    public function store(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = Auth::user();

        // Vérifier l'accès à l'activité
        if (!$activity->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:activity_messages,id',
            'type' => 'sometimes|in:text,announcement',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'string|max:255',
        ]);

        // Vérifier les permissions pour les annonces
        if (isset($validated['type']) && $validated['type'] === 'announcement') {
            $participant = $activity->participants()->where('user_id', $user->id)->first();
            if (!$participant || $participant->role !== 'facilitator') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les facilitateurs peuvent créer des annonces'
                ], 403);
            }
        }

        // Vérifier que le parent existe et appartient à la même activité
        if (isset($validated['parent_id'])) {
            $parentMessage = ActivityMessage::find($validated['parent_id']);
            if (!$parentMessage || $parentMessage->resource_activity_id !== $activity->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message parent invalide'
                ], 422);
            }
        }

        $messageData = [
            'content' => $validated['content'],
            'resource_activity_id' => $activity->id,
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'type' => $validated['type'] ?? 'text',
            'attachments' => $validated['attachments'] ?? null,
        ];

        // Les annonces sont automatiquement épinglées
        if ($messageData['type'] === 'announcement') {
            $messageData['is_pinned'] = true;
        }

        $message = ActivityMessage::create($messageData);
        $message->load('user:id,name,avatar');

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Message créé avec succès'
        ], 201);
    }

    /**
     * Update the specified message.
     */
    public function update(Request $request, ActivityMessage $message): JsonResponse
    {
        $user = Auth::user();

        // Vérifier les permissions d'édition
        if (!$message->canBeEditedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas modifier ce message'
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message->edit($validated['content']);
        $message->load('user:id,name,avatar');

        return response()->json([
            'success' => true,
            'data' => $message->fresh(),
            'message' => 'Message modifié avec succès'
        ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(ActivityMessage $message): JsonResponse
    {
        $user = Auth::user();

        // Vérifier les permissions de suppression
        if (!$message->canBeDeletedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer ce message'
            ], 403);
        }

        // Supprimer les réponses en cascade
        $message->replies()->delete();
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message supprimé avec succès'
        ]);
    }

    /**
     * Pin or unpin a message.
     */
    public function pin(Request $request, ActivityMessage $message): JsonResponse
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur est facilitateur de l'activité
        $participant = $message->activity->participants()->where('user_id', $user->id)->first();
        if (!$participant || $participant->role !== 'facilitator') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les facilitateurs peuvent épingler des messages'
            ], 403);
        }

        $action = $request->get('action', 'toggle');

        switch ($action) {
            case 'pin':
                $message->pin();
                $messageText = 'Message épinglé avec succès';
                break;
            case 'unpin':
                $message->unpin();
                $messageText = 'Message désépinglé avec succès';
                break;
            case 'toggle':
            default:
                if ($message->is_pinned) {
                    $message->unpin();
                    $messageText = 'Message désépinglé avec succès';
                } else {
                    $message->pin();
                    $messageText = 'Message épinglé avec succès';
                }
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $message->fresh(),
            'message' => $messageText
        ]);
    }

    /**
     * Add or remove a reaction to a message.
     */
    public function react(Request $request, ActivityMessage $message): JsonResponse
    {
        $user = Auth::user();

        // Vérifier l'accès à l'activité
        if (!$message->activity->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $validated = $request->validate([
            'reaction' => 'required|string|in:👍,👎,❤️,😂,😮,😢,😡,👏,🎉,🤔',
            'action' => 'sometimes|in:add,remove,toggle',
        ]);

        $reaction = $validated['reaction'];
        $action = $validated['action'] ?? 'toggle';

        switch ($action) {
            case 'add':
                $message->addReaction($user, $reaction);
                break;
            case 'remove':
                $message->removeReaction($user, $reaction);
                break;
            case 'toggle':
            default:
                if ($message->hasReactionFrom($user, $reaction)) {
                    $message->removeReaction($user, $reaction);
                } else {
                    $message->addReaction($user, $reaction);
                }
                break;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reactions' => $message->fresh()->getReactions()
            ],
            'message' => 'Réaction mise à jour avec succès'
        ]);
    }

    /**
     * Store a private message.
     */
    public function storePrivate(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = Auth::user();

        // Vérifier l'accès à l'activité
        if (!$activity->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'recipient_id' => 'required|exists:users,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'string|max:255',
        ]);

        // Vérifier que le destinataire participe à l'activité
        if (!$activity->participants()->where('user_id', $validated['recipient_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Le destinataire ne participe pas à cette activité'
            ], 422);
        }

        // Empêcher l'envoi à soi-même
        if ($validated['recipient_id'] == $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas vous envoyer un message privé'
            ], 422);
        }

        $recipient = User::find($validated['recipient_id']);

        $message = ActivityMessage::createPrivateMessage(
            $activity,
            $user,
            $recipient,
            $validated['content']
        );

        if (isset($validated['attachments'])) {
            $message->update(['attachments' => $validated['attachments']]);
        }

        $message->load(['user:id,name,avatar', 'recipient:id,name,avatar']);

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Message privé envoyé avec succès'
        ], 201);
    }

    /**
     * Get private messages for current user in an activity.
     */
    public function indexPrivate(Request $request, ResourceActivity $activity): JsonResponse
    {
        $user = Auth::user();

        // Vérifier l'accès à l'activité
        if (!$activity->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $query = ActivityMessage::forActivity($activity->id)
            ->private()
            ->forUser($user->id)
            ->with(['user:id,name,avatar', 'recipient:id,name,avatar']);

        // Filtrer par correspondant si spécifié
        if ($request->has('correspondent_id')) {
            $correspondentId = $request->correspondent_id;
            $query->where(function($q) use ($user, $correspondentId) {
                $q->where(function($subQ) use ($user, $correspondentId) {
                    $subQ->where('user_id', $user->id)
                        ->where('recipient_id', $correspondentId);
                })->orWhere(function($subQ) use ($user, $correspondentId) {
                    $subQ->where('user_id', $correspondentId)
                        ->where('recipient_id', $user->id);
                });
            });
        }

        // Marquer les messages reçus comme lus
        if ($request->has('mark_as_read') && $request->mark_as_read) {
            ActivityMessage::forActivity($activity->id)
                ->where('recipient_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        $perPage = $request->get('per_page', 20);
        $messages = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $messages,
            'message' => 'Messages privés récupérés avec succès'
        ]);
    }

    /**
     * Get conversation participants (users who sent/received private messages).
     */
    public function getConversations(ResourceActivity $activity): JsonResponse
    {
        $user = Auth::user();

        // Vérifier l'accès à l'activité
        if (!$activity->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        // Récupérer tous les utilisateurs avec qui l'utilisateur actuel a échangé
        $conversations = ActivityMessage::forActivity($activity->id)
            ->private()
            ->forUser($user->id)
            ->select('user_id', 'recipient_id')
            ->get()
            ->flatMap(function ($message) use ($user) {
                return [$message->user_id, $message->recipient_id];
            })
            ->unique()
            ->reject(function ($userId) use ($user) {
                return $userId == $user->id;
            })
            ->values();

        $users = User::whereIn('id', $conversations)
            ->select('id', 'name', 'avatar')
            ->get()
            ->map(function ($correspondent) use ($activity, $user) {
                // Compter les messages non lus de ce correspondant
                $unreadCount = ActivityMessage::forActivity($activity->id)
                    ->where('user_id', $correspondent->id)
                    ->where('recipient_id', $user->id)
                    ->where('is_read', false)
                    ->count();

                $correspondent->unread_count = $unreadCount;
                return $correspondent;
            });

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Correspondants récupérés avec succès'
        ]);
    }
}
