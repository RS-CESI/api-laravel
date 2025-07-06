<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CanModerate
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

        // Vérifier si l'utilisateur a les droits de modération
        $moderationRoles = ['moderator', 'administrator', 'super-administrator'];

        if (!in_array($userRole, $moderationRoles)) {
            return response()->json([
                'message' => 'Forbidden - Moderation privileges required',
                'required_roles' => $moderationRoles,
                'user_role' => $userRole,
                'status' => 403
            ], 403);
        }

        return $next($request);
    }
}
