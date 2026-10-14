<?php

namespace App\Services\Admin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RoleService
{
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Role::with('permissions')->where('site_id', $siteId);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'LIKE', $search)
                  ->orWhere('code', 'LIKE', $search)
                  ->orWhere('description', 'LIKE', $search);
            });
        }

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        $allowedSorts = ['id', 'code', 'nom', 'statut', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'id';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Role
    {
        $query = $withTrashed ? Role::withTrashed() : Role::query();
        return $query->with('permissions')->where('site_id', $siteId)->where('id', $id)->first();
    }

    public function create(string $siteId, array $data): Role
    {
        $data['site_id'] = $siteId;

        return DB::transaction(function () use ($data) {
            return Role::create($data);
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update($data);
            return $role->fresh(['permissions']);
        });
    }

    public function delete(Role $role, ?string $userId = null): bool
    {
        if ($userId) {
            $role->deleted_by = $userId;
            $role->save();
        }
        return (bool) $role->delete();
    }

    public function restore(string $siteId, string $id): ?Role
    {
        $role = Role::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($role) {
            $role->restore();
            $role->deleted_by = null;
            $role->save();
            return $role->fresh(['permissions']);
        }

        return null;
    }

    /**
     * Synchroniser les permissions d'un rôle (Règle critique : toutes les permissions doivent appartenir au même site).
     */
    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        if (!empty($permissionIds)) {
            // Vérifier que toutes les permissions appartiennent au même site
            $validCount = Permission::where('site_id', $role->site_id)
                ->whereIn('id', $permissionIds)
                ->count();

            if ($validCount !== count(array_unique($permissionIds))) {
                throw new UnprocessableEntityHttpException('Une ou plusieurs permissions sont invalides ou n\'appartiennent pas à ce site.');
            }
        }

        return DB::transaction(function () use ($role, $permissionIds) {
            $role->permissions()->sync($permissionIds);
            return $role->fresh(['permissions']);
        });
    }
}
