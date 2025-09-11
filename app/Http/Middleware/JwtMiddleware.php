<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenInvalidException $e) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token is invalid');
        } catch (TokenExpiredException $e) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token has expired');
        } catch (JWTException $e) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token not provided');
        }

        return $next($request);
    }

    /**
     * Return a JSON error response
     */
    private function jsonErrorResponse($message, $statusCode)
    {
        // Create a raw JSON response to bypass Laravel's error handling
        $jsonResponse = json_encode([
            'status' => false,
            'message' => $message
        ]);

        return new \Illuminate\Http\Response($jsonResponse, $statusCode, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, private',
            'Date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ]);
    }
}
