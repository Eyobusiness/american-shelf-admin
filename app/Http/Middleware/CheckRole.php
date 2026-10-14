<?php

namespace App\Http\Middleware;

use App\Services\Auth\PermissionService;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use ApiResponseTrait;

    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request to verify roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return $this->errorResponse('Non authentifié.', Response::HTTP_UNAUTHORIZED);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!$this->permissionService->hasRole($user, $roles)) {
            return $this->errorResponse('Accès refusé. Vous ne possédez pas le rôle requis pour cette action.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
