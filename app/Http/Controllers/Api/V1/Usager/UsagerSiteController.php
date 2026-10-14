<?php

namespace App\Http\Controllers\Api\V1\Usager;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\UsagerSite\StoreUsagerSiteRequest;
use App\Http\Requests\UsagerSite\UpdateUsagerSiteRequest;
use App\Http\Resources\UsagerSiteResource;
use App\Services\Usager\UsagerSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsagerSiteController extends BaseApiController
{
    public function __construct(
        protected UsagerSiteService $usagerSiteService
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
        $filters = $request->only([
            'search',
            'type',
            'statut',
            'categorie_usager_id',
            'sort_by',
            'sort_direction',
        ]);

        $memberships = $this->usagerSiteService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($memberships, UsagerSiteResource::class, 'Adhésions récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $membership = $this->usagerSiteService->findById($siteId, $id);

        if (!$membership) {
            return $this->errorResponse('Adhésion introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new UsagerSiteResource($membership), 'Détail de l\'adhésion récupéré avec succès.');
    }

    public function store(StoreUsagerSiteRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $membership = $this->usagerSiteService->create($siteId, $validated);

        return $this->successResponse(new UsagerSiteResource($membership), 'Adhésion enregistrée avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateUsagerSiteRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $membership = $this->usagerSiteService->findById($siteId, $id);

        if (!$membership) {
            return $this->errorResponse('Adhésion introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $updated = $this->usagerSiteService->update($membership, $validated);

        return $this->successResponse(new UsagerSiteResource($updated), 'Adhésion mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $membership = $this->usagerSiteService->findById($siteId, $id);

        if (!$membership) {
            return $this->errorResponse('Adhésion introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->usagerSiteService->delete($membership, $userId);

        return $this->successResponse(null, 'Adhésion supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $membership = $this->usagerSiteService->restore($siteId, $id);

        if (!$membership) {
            return $this->errorResponse('Adhésion supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new UsagerSiteResource($membership), 'Adhésion restaurée avec succès.');
    }
}
