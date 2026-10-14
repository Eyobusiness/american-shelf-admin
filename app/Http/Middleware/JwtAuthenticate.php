<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request with guard support.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $guard
     */
    public function handle(Request $request, Closure $next, string $guard = 'api'): Response
    {
        try {
            $user = Auth::guard($guard)->authenticate();

            if (!$user) {
                return $this->errorResponse('Compte introuvable ou inactif.', Response::HTTP_UNAUTHORIZED);
            }
        } catch (TokenExpiredException $e) {
            return $this->errorResponse('Le token a expiré. Veuillez vous reconnecter.', Response::HTTP_UNAUTHORIZED);
        } catch (TokenInvalidException $e) {
            return $this->errorResponse('Le token fourni est invalide.', Response::HTTP_UNAUTHORIZED);
        } catch (JWTException $e) {
            return $this->errorResponse('Jeton d’authentification absent ou non reconnu.', Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            return $this->errorResponse('Accès non authentifié.', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
