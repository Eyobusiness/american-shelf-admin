<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\UpdateAdminPasswordRequest;
use App\Http\Requests\Auth\UpdateAdminProfileRequest;
use App\Http\Resources\UtilisateurResource;
use App\Services\Auth\AuthAdminService;
use Illuminate\Http\JsonResponse;

class AdminAuthController extends BaseApiController
{
    public function __construct(
        protected AuthAdminService $authAdminService
    ) {}

    /**
     * Authenticate administrative user.
     *
     * @param AdminLoginRequest $request
     * @return JsonResponse
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $result = $this->authAdminService->login(
            email: $credentials['email'],
            password: $credentials['mot_de_passe'],
            siteId: $credentials['site_id'] ?? null
        );

        return $this->successResponse([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'utilisateur' => new UtilisateurResource($result['utilisateur']),
        ], 'Connexion administrateur réussie.');
    }

    /**
     * Get authenticated administrative profile.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = $this->authAdminService->me();

        if (!$user) {
            return $this->errorResponse('Utilisateur non connecté.', 401);
        }

        return $this->successResponse(
            new UtilisateurResource($user),
            'Profil administrateur récupéré avec succès.'
        );
    }

    /**
     * Mettre à jour les informations du profil connecté.
     *
     * @param UpdateAdminProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateAdminProfileRequest $request): JsonResponse
    {
        $user = $this->authAdminService->updateProfile($request->validated());

        return $this->successResponse(
            new UtilisateurResource($user),
            'Profil administrateur mis à jour avec succès.'
        );
    }

    /**
     * Modifier le mot de passe de l'administrateur connecté.
     *
     * @param UpdateAdminPasswordRequest $request
     * @return JsonResponse
     */
    public function updatePassword(UpdateAdminPasswordRequest $request): JsonResponse
    {
        $this->authAdminService->updatePassword(
            $request->validated('mot_de_passe_actuel'),
            $request->validated('mot_de_passe')
        );

        return $this->successResponse(null, 'Mot de passe modifié avec succès.');
    }

    /**
     * Refresh administrative JWT token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        $result = $this->authAdminService->refresh();

        return $this->successResponse([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'utilisateur' => new UtilisateurResource($result['utilisateur']),
        ], 'Jeton actualisé avec succès.');
    }

    /**
     * Logout administrative user.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        $this->authAdminService->logout();

        return $this->successResponse(null, 'Déconnexion effectuée avec succès.');
    }
}