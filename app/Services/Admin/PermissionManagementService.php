<?php

namespace App\Services\Admin;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\SousMenu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PermissionManagementService
{
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Permission::with(['menu', 'sousMenu'])->where('site_id', $siteId);

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['menu_id'])) {
            $query->where('menu_id', $filters['menu_id']);
        }

        if (!empty($filters['sous_menu_id'])) {
            $query->where('sous_menu_id', $filters['sous_menu_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', $search)
                  ->orWhere('libelle', 'LIKE', $search)
                  ->orWhere('action', 'LIKE', $search);
            });
        }

        $allowedSorts = ['id', 'code', 'action', 'libelle', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'id';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    public function findById(string $siteId, string $id): ?Permission
    {
        return Permission::with(['menu', 'sousMenu'])->where('site_id', $siteId)->where('id', $id)->first();
    }

    public function create(string $siteId, array $data): Permission
    {
        $this->validateRelations($siteId, $data['menu_id'] ?? null, $data['sous_menu_id'] ?? null);

        $data['site_id'] = $siteId;

        return DB::transaction(function () use ($data) {
            return Permission::create($data);
        });
    }

    public function update(Permission $permission, array $data): Permission
    {
        $menuId = array_key_exists('menu_id', $data) ? $data['menu_id'] : $permission->menu_id;
        $sousMenuId = array_key_exists('sous_menu_id', $data) ? $data['sous_menu_id'] : $permission->sous_menu_id;

        $this->validateRelations($permission->site_id, $menuId, $sousMenuId);

        return DB::transaction(function () use ($permission, $data) {
            $permission->update($data);
            return $permission->fresh(['menu', 'sousMenu']);
        });
    }

    public function delete(Permission $permission): bool
    {
        return (bool) $permission->delete();
    }

    protected function validateRelations(string $siteId, ?string $menuId, ?string $sousMenuId): void
    {
        if ($menuId) {
            $menu = Menu::where('site_id', $siteId)->where('id', $menuId)->first();
            if (!$menu) {
                throw new UnprocessableEntityHttpException('Le menu sélectionné n\'appartient pas à ce site.');
            }
        }

        if ($sousMenuId) {
            $sousMenu = SousMenu::where('site_id', $siteId)->where('id', $sousMenuId)->first();
            if (!$sousMenu) {
                throw new UnprocessableEntityHttpException('Le sous-menu sélectionné n\'appartient pas à ce site.');
            }

            if ($menuId && (string) $sousMenu->menu_id !== (string) $menuId) {
                throw new UnprocessableEntityHttpException('Le sous-menu sélectionné n\'appartient pas au menu choisi.');
            }
        }
    }
}
