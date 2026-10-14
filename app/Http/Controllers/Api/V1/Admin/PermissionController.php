<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\Admin\PermissionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends BaseApiController
{
    public function __construct(
        protected PermissionManagementService $permissionService
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
        $filters = $request->only(['search', 'action', 'menu_id', 'sous_menu_id', 'sort_by', 'sort_direction']);

        $permissions = $this->permissionService->getAllPaginated($siteId, $filters, $perPage);

        return $this->paginatedResponse($permissions, PermissionResource::class, 'Permissions récupérées avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $permission = $this->permissionService->findById($siteId, $id);

        if (!$permission) {
            return $this->errorResponse('Permission introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new PermissionResource($permission), 'Détail de la permission récupéré avec succès.');
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $permission = $this->permissionService->create($siteId, $request->validated());

        return $this->successResponse(new PermissionResource($permission), 'Permission créée avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdatePermissionRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $permission = $this->permissionService->findById($siteId, $id);

        if (!$permission) {
            return $this->errorResponse('Permission introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->permissionService->update($permission, $request->validated());

        return $this->successResponse(new PermissionResource($updated), 'Permission mise à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $permission = $this->permissionService->findById($siteId, $id);

        if (!$permission) {
            return $this->errorResponse('Permission introuvable.', Response::HTTP_NOT_FOUND);
        }

        $this->permissionService->delete($permission);

        return $this->successResponse(null, 'Permission supprimée avec succès.');
    }
}
