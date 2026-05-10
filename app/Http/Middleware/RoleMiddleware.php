<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // cek apakah user login dengan auth:api JWT
        if (!auth('api')->check()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // cek role user
        if (!in_array(auth('api')->user()->role, $roles)) {
            return response()->json([
                'message' => 'Forbidden - role tidak sesuai',
                'debug_your_role' => auth('api')->user()->role,
                'debug_allowed_roles' => $roles,
                'debug_url' => $request->url()
            ], 403);
        }

        return $next($request);
    }
}