<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Menu\StoreMenuRequest;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Services\Admin\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MenuController extends BaseApiController
{
    public function __construct(
        protected MenuService $menuService
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

        $menus = $this->menuService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($menus, MenuResource::class, 'Menus récupérés avec succès.');
    }

    public function tree(Request $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $tree = $this->menuService->getTree($siteId);

        return $this->successResponse(MenuResource::collection($tree), 'Arborescence des menus récupérée avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $menu = $this->menuService->findById($siteId, $id);

        if (!$menu) {
            return $this->errorResponse('Menu introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new MenuResource($menu), 'Détail du menu récupéré avec succès.');
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $menu = $this->menuService->create($siteId, $request->validated());

        return $this->successResponse(new MenuResource($menu), 'Menu créé avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateMenuRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $menu = $this->menuService->findById($siteId, $id);

        if (!$menu) {
            return $this->errorResponse('Menu introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->menuService->update($menu, $request->validated());

        return $this->successResponse(new MenuResource($updated), 'Menu mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $menu = $this->menuService->findById($siteId, $id);

        if (!$menu) {
            return $this->errorResponse('Menu introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->menuService->delete($menu, $userId);

        return $this->successResponse(null, 'Menu supprimé avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $menu = $this->menuService->restore($siteId, $id);

        if (!$menu) {
            return $this->errorResponse('Menu supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new MenuResource($menu), 'Menu restauré avec succès.');
    }
}
