<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request to verify that the authenticated user is a SUPERADMIN.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user('api') ?? Auth::guard('api')->user();
        } catch (\Throwable) {
            return $this->errorResponse('Non authentifié. Jeton manquant, invalide ou expiré.', Response::HTTP_UNAUTHORIZED);
        }

        if (!$user) {
            return $this->errorResponse('Non authentifié. Jeton manquant, invalide ou expiré.', Response::HTTP_UNAUTHORIZED);
        }

        $roleCode = strtoupper($user->role->code ?? '');

        if ($roleCode !== 'SUPERADMIN') {
            return $this->errorResponse('Accès refusé. Vous devez être Super Administrateur pour effectuer cette action.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
