<?php

namespace App\Services\Admin;

use App\Models\Menu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MenuService
{
    /**
     * Liste paginée avec recherche, tri par order par défaut.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Menu::with('sousMenus')->where('site_id', $siteId);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('libelle', 'LIKE', $search)
                  ->orWhere('code', 'LIKE', $search)
                  ->orWhere('reference', 'LIKE', $search);
            });
        }

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        $allowedSorts = ['id', 'code', 'libelle', 'order', 'statut', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'order';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un menu par ID pour le site.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Menu
    {
        $query = $withTrashed ? Menu::withTrashed() : Menu::query();
        return $query->with('sousMenus')->where('site_id', $siteId)->where('id', $id)->first();
    }

    /**
     * Obtenir l'arborescence complète ordonnée des menus et sous-menus actifs.
     */
    public function getTree(string $siteId): \Illuminate\Database\Eloquent\Collection
    {
        return Menu::with(['sousMenus' => function ($q) {
            $q->where('statut', 'ACTIF')->orderBy('order', 'asc');
        }])
        ->where('site_id', $siteId)
        ->where('statut', 'ACTIF')
        ->orderBy('order', 'asc')
        ->get();
    }

    /**
     * Créer un menu pour le site.
     */
    public function create(string $siteId, array $data): Menu
    {
        $data['site_id'] = $siteId;

        return DB::transaction(function () use ($data) {
            return Menu::create($data);
        });
    }

    /**
     * Mettre à jour un menu.
     */
    public function update(Menu $menu, array $data): Menu
    {
        return DB::transaction(function () use ($menu, $data) {
            $menu->update($data);
            return $menu->fresh(['sousMenus']);
        });
    }

    /**
     * Suppression logique d'un menu.
     */
    public function delete(Menu $menu, ?string $userId = null): bool
    {
        if ($userId) {
            $menu->deleted_by = $userId;
            $menu->save();
        }
        return (bool) $menu->delete();
    }

    /**
     * Restaurer un menu.
     */
    public function restore(string $siteId, string $id): ?Menu
    {
        $menu = Menu::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($menu) {
            $menu->restore();
            $menu->deleted_by = null;
            $menu->save();
            return $menu->fresh(['sousMenus']);
        }

        return null;
    }
}
