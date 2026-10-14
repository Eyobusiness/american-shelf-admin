<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Utilisateur\StoreUtilisateurRequest;
use App\Http\Requests\Utilisateur\UpdateUtilisateurRequest;
use App\Http\Resources\UtilisateurResource;
use App\Services\Admin\UtilisateurManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UtilisateurController extends BaseApiController
{
    public function __construct(
        protected UtilisateurManagementService $utilisateurService
    ) {}

    protected function getSiteId(Request $request): string
    {
        $siteId = $request->attributes->get('current_site_id') ?? $request->user()?->site_id;
        if (!$siteId) {
            abort(Response::HTTP_FORBIDDEN, 'Contexte de site (tenant) manquant ou non autorisé.');
        }
        return (string) $siteId;
    }

    public function index(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only(['search', 'role_id', 'statut', 'sort_by', 'sort_direction']);

        $utilisateurs = $this->utilisateurService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($utilisateurs, UtilisateurResource::class, 'Utilisateurs récupérés avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $user = $this->utilisateurService->findById($siteId, $id);

        if (!$user) {
            return $this->errorResponse('Utilisateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new UtilisateurResource($user), 'Détail de l\'utilisateur récupéré avec succès.');
    }

    public function store(StoreUtilisateurRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $user = $this->utilisateurService->create($siteId, $request->validated());

        return $this->successResponse(new UtilisateurResource($user), 'Utilisateur créé avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateUtilisateurRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $user = $this->utilisateurService->findById($siteId, $id);

        if (!$user) {
            return $this->errorResponse('Utilisateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->utilisateurService->update($user, $request->validated());

        return $this->successResponse(new UtilisateurResource($updated), 'Utilisateur mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $user = $this->utilisateurService->findById($siteId, $id);

        if (!$user) {
            return $this->errorResponse('Utilisateur introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->utilisateurService->delete($user, $userId);

        return $this->successResponse(null, 'Utilisateur supprimé avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $user = $this->utilisateurService->restore($siteId, $id);

        if (!$user) {
            return $this->errorResponse('Utilisateur supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new UtilisateurResource($user), 'Utilisateur restauré avec succès.');
    }
}
