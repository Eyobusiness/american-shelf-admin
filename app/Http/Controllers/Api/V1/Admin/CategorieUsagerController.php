<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\CategorieUsager\StoreCategorieUsagerRequest;
use App\Http\Requests\CategorieUsager\UpdateCategorieUsagerRequest;
use App\Http\Resources\CategorieUsagerResource;
use App\Services\Admin\CategorieUsagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategorieUsagerController extends BaseApiController
{
    public function __construct(
        protected CategorieUsagerService $categorieService
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
        $filters = $request->only(['search', 'statut', 'sort_by', 'sort_direction']);

        $categories = $this->categorieService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($categories, CategorieUsagerResource::class, 'Catégories d\'usagers récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->findById($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie d\'usager introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new CategorieUsagerResource($category), 'Détail de la catégorie d\'usager récupéré avec succès.');
    }

    public function store(StoreCategorieUsagerRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->create($siteId, $request->validated());

        return $this->successResponse(new CategorieUsagerResource($category), 'Catégorie d\'usager créée avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateCategorieUsagerRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->findById($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie d\'usager introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->categorieService->update($category, $request->validated());

        return $this->successResponse(new CategorieUsagerResource($updated), 'Catégorie d\'usager mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->findById($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie d\'usager introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->categorieService->delete($category, $userId);

        return $this->successResponse(null, 'Catégorie d\'usager supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->restore($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie d\'usager supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new CategorieUsagerResource($category), 'Catégorie d\'usager restaurée avec succès.');
    }
}
