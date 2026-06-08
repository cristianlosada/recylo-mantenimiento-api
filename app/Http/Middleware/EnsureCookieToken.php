<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCookieToken
{
    /**
     * Handle an incoming request.
     * 
     * Este middleware permite que Sanctum lea el token desde la cookie
     * en lugar de solo desde el header Authorization.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si la cookie auth_token existe y no hay header Authorization
        if ($request->hasCookie('auth_token') && !$request->bearerToken()) {
            $token = $request->cookie('auth_token');
            
            // Agregar el token como header Authorization para que Sanctum lo procese
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
