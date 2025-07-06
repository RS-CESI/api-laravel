<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Resource;
use Illuminate\Http\Response;

class CanEditResource
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return JsonResponse
     */
    public function handle(Request $request, Closure $next): JsonResponse
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized - Authentication required',
                'status' => 401
            ], 401);
        }

        $user = $request->user();
        $userRole = $user->role->name ?? null;

        // Super-admins et admins peuvent tout éditer
        if (in_array($userRole, ['super-administrator', 'administrator'])) {
            return $next($request);
        }

        // Récupérer la ressource depuis la route
        $resourceId = $request->route('resource');

        if ($resourceId) {
            $resource = Resource::find($resourceId);

            if (!$resource) {
                return response()->json([
                    'message' => 'Resource not found',
                    'status' => 404
                ], 404);
            }

            // Vérifier si l'utilisateur est le créateur de la ressource
            if ($resource->created_by !== $user->id) {
                return response()->json([
                    'message' => 'Forbidden - You can only edit your own resources',
                    'status' => 403
                ], 403);
            }

            // Vérifier le statut de la ressource
            if (in_array($resource->status, ['published', 'suspended']) && $userRole !== 'moderator') {
                return response()->json([
                    'message' => 'Forbidden - Cannot edit published or suspended resources',
                    'status' => 403
                ], 403);
            }
        }

        return $next($request);
    }
}
