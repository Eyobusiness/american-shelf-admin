<?php

namespace App\Http\Middleware;

use App\Services\Auth\PermissionService;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    use ApiResponseTrait;

    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request to verify fine-grained permissions.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return $this->errorResponse('Non authentifié.', Response::HTTP_UNAUTHORIZED);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        $hasAccess = false;
        foreach ($permissions as $permissionCode) {
            if ($this->permissionService->hasPermission($user, $permissionCode)) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            return $this->errorResponse('Accès refusé. Vous n\'avez pas la permission requise.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
