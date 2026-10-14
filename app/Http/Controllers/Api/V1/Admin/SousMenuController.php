<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\SousMenu\StoreSousMenuRequest;
use App\Http\Requests\SousMenu\UpdateSousMenuRequest;
use App\Http\Resources\SousMenuResource;
use App\Services\Admin\SousMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SousMenuController extends BaseApiController
{
    public function __construct(
        protected SousMenuService $sousMenuService
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
        $filters = $request->only(['search', 'menu_id', 'statut', 'sort_by', 'sort_direction']);

        $sousMenus = $this->sousMenuService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($sousMenus, SousMenuResource::class, 'Sous-menus récupérés avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sousMenu = $this->sousMenuService->findById($siteId, $id);

        if (!$sousMenu) {
            return $this->errorResponse('Sous-menu introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SousMenuResource($sousMenu), 'Détail du sous-menu récupéré avec succès.');
    }

    public function store(StoreSousMenuRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sousMenu = $this->sousMenuService->create($siteId, $request->validated());

        return $this->successResponse(new SousMenuResource($sousMenu), 'Sous-menu créé avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateSousMenuRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sousMenu = $this->sousMenuService->findById($siteId, $id);

        if (!$sousMenu) {
            return $this->errorResponse('Sous-menu introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->sousMenuService->update($sousMenu, $request->validated());

        return $this->successResponse(new SousMenuResource($updated), 'Sous-menu mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sousMenu = $this->sousMenuService->findById($siteId, $id);

        if (!$sousMenu) {
            return $this->errorResponse('Sous-menu introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->sousMenuService->delete($sousMenu, $userId);

        return $this->successResponse(null, 'Sous-menu supprimé avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $sousMenu = $this->sousMenuService->restore($siteId, $id);

        if (!$sousMenu) {
            return $this->errorResponse('Sous-menu supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new SousMenuResource($sousMenu), 'Sous-menu restauré avec succès.');
    }
}
