<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\UsagerLoginRequest;
use App\Http\Resources\UsagerResource;
use App\Services\Auth\AuthUsagerService;
use Illuminate\Http\JsonResponse;

class UsagerAuthController extends BaseApiController
{
    public function __construct(
        protected AuthUsagerService $authUsagerService
    ) {}

    /**
     * Authenticate public usager.
     *
     * @param UsagerLoginRequest $request
     * @return JsonResponse
     */
    public function login(UsagerLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $result = $this->authUsagerService->login(
            identifiant: $credentials['identifiant'],
            password: $credentials['mot_de_passe']
        );

        return $this->successResponse([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'usager' => new UsagerResource($result['usager']),
        ], 'Connexion usager réussie.');
    }

    /**
     * Get authenticated usager profile.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $usager = $this->authUsagerService->me();

        if (!$usager) {
            return $this->errorResponse('Usager non connecté.', 401);
        }

        return $this->successResponse(
            new UsagerResource($usager),
            'Profil usager récupéré avec succès.'
        );
    }

    /**
     * Refresh usager JWT token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        $result = $this->authUsagerService->refresh();

        return $this->successResponse([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'usager' => new UsagerResource($result['usager']),
        ], 'Jeton actualisé avec succès.');
    }

    /**
     * Logout usager.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        $this->authUsagerService->logout();

        return $this->successResponse(null, 'Déconnexion effectuée avec succès.');
    }
}
