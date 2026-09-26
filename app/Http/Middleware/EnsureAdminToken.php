<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminToken = $request->header('X-Admin-Secret-Token');
        $secretToken = env('ADMIN_SECRET_TOKEN', 'admin-token-123');

        if ($adminToken !== $secretToken) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
