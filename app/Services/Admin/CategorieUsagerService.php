<?php

namespace App\Services\Admin;

use App\Models\CategorieUsager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CategorieUsagerService
{
    /**
     * Liste paginée avec recherche et filtres pour un site donné.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = CategorieUsager::where('site_id', $siteId);

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

    /**
     * Trouver par ID au sein d'un site.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?CategorieUsager
    {
        $query = $withTrashed ? CategorieUsager::withTrashed() : CategorieUsager::query();
        return $query->where('site_id', $siteId)->where('id', $id)->first();
    }

    /**
     * Créer une catégorie usager rattachée au site.
     */
    public function create(string $siteId, array $data): CategorieUsager
    {
        $data['site_id'] = $siteId;

        return DB::transaction(function () use ($data) {
            return CategorieUsager::create($data);
        });
    }

    /**
     * Mettre à jour une catégorie usager.
     */
    public function update(CategorieUsager $category, array $data): CategorieUsager
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update($data);
            return $category->fresh();
        });
    }

    /**
     * Suppression logique.
     */
    public function delete(CategorieUsager $category, ?string $userId = null): bool
    {
        if ($userId) {
            $category->deleted_by = $userId;
            $category->save();
        }
        return (bool) $category->delete();
    }

    /**
     * Restauration.
     */
    public function restore(string $siteId, string $id): ?CategorieUsager
    {
        $category = CategorieUsager::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($category) {
            $category->restore();
            $category->deleted_by = null;
            $category->save();
            return $category;
        }

        return null;
    }
}
