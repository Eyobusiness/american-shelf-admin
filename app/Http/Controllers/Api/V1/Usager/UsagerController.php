<?php

namespace App\Http\Controllers\Api\V1\Usager;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Usager\StoreUsagerRequest;
use App\Http\Requests\Usager\UpdateUsagerRequest;
use App\Http\Resources\UsagerResource;
use App\Services\Usager\UsagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsagerController extends BaseApiController
{
    public function __construct(
        protected UsagerService $usagerService
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

        $usagers = $this->usagerService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($usagers, UsagerResource::class, 'Usagers récupérés avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $usager = $this->usagerService->findById($siteId, $id);

        if (!$usager) {
            return $this->errorResponse('Usager introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new UsagerResource($usager), 'Détail de l\'usager récupéré avec succès.');
    }

    public function store(StoreUsagerRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $validated = $request->validated();

        $usager = $this->usagerService->create($siteId, $validated);

        return $this->successResponse(new UsagerResource($usager), 'Usager enregistré avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateUsagerRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $usager = $this->usagerService->findById($siteId, $id);

        if (!$usager) {
            return $this->errorResponse('Usager introuvable.', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $updated = $this->usagerService->update($siteId, $usager, $validated);

        return $this->successResponse(new UsagerResource($updated), 'Usager mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $usager = $this->usagerService->findById($siteId, $id);

        if (!$usager) {
            return $this->errorResponse('Usager introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->usagerService->delete($siteId, $usager, $userId);

        return $this->successResponse(null, 'Usager détaché du site avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $usager = $this->usagerService->restore($siteId, $id);

        if (!$usager) {
            return $this->errorResponse('Usager supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new UsagerResource($usager), 'Usager restauré avec succès.');
    }
}
