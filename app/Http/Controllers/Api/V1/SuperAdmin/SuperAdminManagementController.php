<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\SuperAdmin\StoreSuperAdminUserRequest;
use App\Http\Requests\SuperAdmin\UpdateSuperAdminStatusRequest;
use App\Http\Requests\SuperAdmin\UpdateSuperAdminUserRequest;
use App\Http\Resources\UtilisateurResource;
use App\Services\SuperAdmin\SuperAdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminManagementController extends BaseApiController
{
    public function __construct(
        protected SuperAdminUserService $superAdminService
    ) {}

    /**
     * Liste paginée des comptes Super Administrateurs.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only(['search', 'statut', 'sort_by', 'sort_direction']);

        $users = $this->superAdminService->getAllSuperAdmins($filters, $perPage);

        return $this->paginatedResponse($users, UtilisateurResource::class, 'Comptes Super Administrateurs récupérés avec succès.');
    }

    /**
     * Création d'un nouveau compte Super Administrateur.
     */
    public function store(StoreSuperAdminUserRequest $request): JsonResponse
    {
        $user = $this->superAdminService->createSuperAdmin($request->validated());

        return $this->createdResponse(
            new UtilisateurResource($user->load('role', 'site')),
            'Compte Super Administrateur créé avec succès.'
        );
    }

    /**
     * Détails d'un compte Super Administrateur.
     */
    public function show(string $id): JsonResponse
    {
        $user = $this->superAdminService->getSuperAdminById($id);

        if (!$user) {
            return $this->errorResponse('Compte Super Administrateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            new UtilisateurResource($user),
            'Détails du compte Super Administrateur récupérés avec succès.'
        );
    }

    /**
     * Modifier les informations d'un Super Administrateur.
     */
    public function update(UpdateSuperAdminUserRequest $request, string $id): JsonResponse
    {
        $user = $this->superAdminService->getSuperAdminById($id);

        if (!$user) {
            return $this->errorResponse('Compte Super Administrateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $currentUserId = (string) ($request->user('api')?->id ?? '');
        $updatedUser = $this->superAdminService->updateSuperAdmin($user, $request->validated(), $currentUserId);

        return $this->successResponse(
            new UtilisateurResource($updatedUser),
            'Compte Super Administrateur mis à jour avec succès.'
        );
    }

    /**
     * Modifier le statut d'un compte Super Administrateur (ACTIF, INACTIF, BLOQUE).
     */
    public function updateStatus(UpdateSuperAdminStatusRequest $request, string $id): JsonResponse
    {
        $user = $this->superAdminService->getSuperAdminById($id);

        if (!$user) {
            return $this->errorResponse('Compte Super Administrateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $currentUserId = (string) ($request->user('api')?->id ?? '');
        $updatedUser = $this->superAdminService->updateStatus($user, $request->validated('statut'), $currentUserId);

        return $this->successResponse(
            new UtilisateurResource($updatedUser),
            'Statut du compte Super Administrateur modifié avec succès.'
        );
    }

    /**
     * Supprimer logiquement un compte Super Administrateur.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $this->superAdminService->getSuperAdminById($id);

        if (!$user) {
            return $this->errorResponse('Compte Super Administrateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $currentUserId = (string) ($request->user('api')?->id ?? '');
        $this->superAdminService->deleteSuperAdmin($user, $currentUserId);

        return $this->successResponse(null, 'Compte Super Administrateur supprimé avec succès.');
    }
}
