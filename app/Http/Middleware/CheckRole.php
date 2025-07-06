<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param  string  ...$roles
     * @return JsonResponse
     */
    public function handle(Request $request, Closure $next, ...$roles): JsonResponse
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized - Authentication required',
                'status' => 401
            ], 401);
        }

        $user = $request->user();
        $userRole = $user->role->name ?? null;

        if (!$userRole || !in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Forbidden - Insufficient permissions',
                'required_roles' => $roles,
                'user_role' => $userRole,
                'status' => 403
            ], 403);
        }

        return $next($request);
    }
}
