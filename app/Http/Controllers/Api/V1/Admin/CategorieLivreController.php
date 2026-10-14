<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\CategorieLivre\StoreCategorieLivreRequest;
use App\Http\Requests\CategorieLivre\UpdateCategorieLivreRequest;
use App\Http\Resources\CategorieLivreResource;
use App\Services\Admin\CategorieLivreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategorieLivreController extends BaseApiController
{
    public function __construct(
        protected CategorieLivreService $categorieService
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

        return $this->paginatedResponse($categories, CategorieLivreResource::class, 'Catégories de livres récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->findById($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie de livre introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new CategorieLivreResource($category), 'Détail de la catégorie de livre récupéré avec succès.');
    }

    public function store(StoreCategorieLivreRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->create($siteId, $request->validated());

        return $this->successResponse(new CategorieLivreResource($category), 'Catégorie de livre créée avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateCategorieLivreRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->findById($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie de livre introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->categorieService->update($category, $request->validated());

        return $this->successResponse(new CategorieLivreResource($updated), 'Catégorie de livre mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->findById($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie de livre introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->categorieService->delete($category, $userId);

        return $this->successResponse(null, 'Catégorie de livre supprimée avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $category = $this->categorieService->restore($siteId, $id);

        if (!$category) {
            return $this->errorResponse('Catégorie de livre supprimée introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new CategorieLivreResource($category), 'Catégorie de livre restaurée avec succès.');
    }
}
