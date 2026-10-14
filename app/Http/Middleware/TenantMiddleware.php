<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request for multi-tenancy context.
     * Prepares the site/tenant context without hardcoding business logic yet.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? Auth::guard('api')->user();

        // 1. Si utilisateur authentifié
        if ($user) {
            $isSuperAdmin = false;
            if ($user->role) {
                $code = strtoupper($user->role->code);
                $isSuperAdmin = ($code === 'SUPERADMIN');
            }

            // Un super-admin peut éventuellement spécifier un site via X-Site-ID
            $requestedSiteId = $request->header('X-Site-ID') ?? $request->header('X-Tenant-ID');

            if ($isSuperAdmin && $requestedSiteId) {
                $siteId = (string) $requestedSiteId;
            } else {
                $siteId = $user->site_id ? (string) $user->site_id : null;
            }
        } else {
            // Utilisateur non connecté ou usager public : utilisation des en-têtes
            $headerSite = $request->header('X-Site-ID') ?? $request->header('X-Tenant-ID');
            $siteId = $headerSite ? (string) $headerSite : null;
        }

        if ($siteId) {
            $request->attributes->set('current_site_id', $siteId);
        }

        return $next($request);
    }
}
