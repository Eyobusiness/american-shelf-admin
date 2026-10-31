<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends BaseApiController
{
    public function __construct(
        protected RoleService $roleService
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

        $roles = $this->roleService->getAllPaginated($siteId, $filters, $perPage, $request->user());

        return $this->paginatedResponse($roles, RoleResource::class, 'Rôles récupérés avec succès.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $role = $this->roleService->findById($siteId, $id, false, $request->user());

        if (!$role) {
            return $this->errorResponse('Rôle introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new RoleResource($role), 'Détail du rôle récupéré avec succès.');
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $role = $this->roleService->create($siteId, $request->validated(), $request->user());

        return $this->successResponse(new RoleResource($role), 'Rôle créé avec succès.', Response::HTTP_CREATED);
    }

    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $role = $this->roleService->findById($siteId, $id, false, $request->user());

        if (!$role) {
            return $this->errorResponse('Rôle introuvable.', Response::HTTP_NOT_FOUND);
        }

        $updated = $this->roleService->update($role, $request->validated(), $request->user());

        return $this->successResponse(new RoleResource($updated), 'Rôle mis à jour avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $role = $this->roleService->findById($siteId, $id, false, $request->user());

        if (!$role) {
            return $this->errorResponse('Rôle introuvable.', Response::HTTP_NOT_FOUND);
        }

        $userId = $request->user()?->id;
        $this->roleService->delete($role, $userId, $request->user());

        return $this->successResponse(null, 'Rôle supprimé avec succès.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $role = $this->roleService->restore($siteId, $id);

        if (!$role) {
            return $this->errorResponse('Rôle supprimé introuvable pour restauration.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new RoleResource($role), 'Rôle restauré avec succès.');
    }

    /**
     * Synchroniser les permissions du rôle.
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, string $id): JsonResponse
    {
        $siteId = $this->getSiteId($request);
        $role = $this->roleService->findById($siteId, $id);

        if (!$role) {
            return $this->errorResponse('Rôle introuvable.', Response::HTTP_NOT_FOUND);
        }

        $permissionIds = $request->validated()['permission_ids'];
        $updatedRole = $this->roleService->syncPermissions($role, $permissionIds);

        return $this->successResponse(new RoleResource($updatedRole), 'Permissions du rôle synchronisées avec succès.');
    }
}
