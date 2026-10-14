<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Services\Admin\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteController extends BaseApiController
{
    public function __construct(
        protected SiteService $siteService
    ) {}

    /**
     * Liste paginée des sites.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only(['search', 'statut', 'sort_by', 'sort_direction']);

        $sites = $this->siteService->getAllPaginated($filters, $perPage);

        return $this->paginatedResponse($sites, SiteResource::class, 'Sites récupérés avec succès.');
    }

    /**
     * Détail d'un site.
     */
    public function show(string $id): JsonResponse
    {
        $site = $this->siteService->findById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SiteResource($site), 'Détail du site récupéré avec succès.');
    }

    /**
     * Création d'un site.
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $site = $this->siteService->create($request->validated());

        return $this->successResponse(new SiteResource($site), 'Site créé avec succès.', Response::HTTP_CREATED);
    }

    /**
     * Mise à jour d'un site.
     */
    public function update(UpdateSiteRequest $request, string $id): JsonResponse
    {
        $site = $this->siteService->findById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updatedSite = $this->siteService->update($site, $request->validated());

        return $this->successResponse(new SiteResource($updatedSite), 'Site mis à jour avec succès.');
    }

    /**
     * Suppression logique d'un site.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $site = $this->siteService->findById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->siteService->delete($site, $userId);

        return $this->successResponse(null, 'Site supprimé avec succès.');
    }

    /**
     * Restauration d'un site supprimé.
     */
    public function restore(string $id): JsonResponse
    {
        $site = $this->siteService->restore($id);

        if (!$site) {
            return $this->errorResponse('Site supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SiteResource($site), 'Site restauré avec succès.');
    }

    /**
     * Upload et mise à jour du logo d'un site.
     */
    public function uploadLogo(\App\Http\Requests\Site\UploadSiteLogoRequest $request, string $id): JsonResponse
    {
        $site = $this->siteService->findById($id);

        if (!$site) {
            return $this->errorResponse('Site introuvable.', Response::HTTP_NOT_FOUND);
        }

        $site = $this->siteService->updateLogo($site, $request->file('logo'));

        return $this->successResponse(new SiteResource($site), 'Logo du site mis à jour avec succès.');
    }
    /**
     * Informations publiques du site principal (pour la page de connexion et le branding).
     */
    public function publicSite(): JsonResponse
    {
        $site = \App\Models\Site::query()->where('code', 'ASA-ABOBO')->first()
            ?? \App\Models\Site::query()->first();

        if (!$site) {
            return $this->errorResponse('Aucun site configuré.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SiteResource($site), 'Informations publiques du site récupérées avec succès.');
    }
}