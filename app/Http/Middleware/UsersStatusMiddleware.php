<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;
use Symfony\Component\HttpFoundation\Response;

class UsersStatusMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if ($user->status === 0) {
            return response()->json([
                'status' => false,
                'message' => 'User Not Active'
            ]);
        }

        return $next($request);
    }
}
