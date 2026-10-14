<?php

namespace App\Services\Admin;

use App\Models\Menu;
use App\Models\SousMenu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SousMenuService
{
    /**
     * Liste paginée des sous-menus pour le site.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = SousMenu::with('menu')->where('site_id', $siteId);

        if (!empty($filters['menu_id'])) {
            $query->where('menu_id', $filters['menu_id']);
        }

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
     * Trouver par ID au sein du site.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?SousMenu
    {
        $query = $withTrashed ? SousMenu::withTrashed() : SousMenu::query();
        return $query->with('menu')->where('site_id', $siteId)->where('id', $id)->first();
    }

    /**
     * Créer un sous-menu avec vérification stricte du menu parent du même site.
     */
    public function create(string $siteId, array $data): SousMenu
    {
        // Validation stricte multi-tenant
        $menu = Menu::where('site_id', $siteId)->where('id', $data['menu_id'])->first();
        if (!$menu) {
            throw new UnprocessableEntityHttpException('Le menu parent n\'appartient pas à ce site.');
        }

        $data['site_id'] = $siteId;

        return DB::transaction(function () use ($data) {
            return SousMenu::create($data);
        });
    }

    /**
     * Mettre à jour un sous-menu.
     */
    public function update(SousMenu $sousMenu, array $data): SousMenu
    {
        if (!empty($data['menu_id']) && (string) $data['menu_id'] !== (string) $sousMenu->menu_id) {
            $menu = Menu::where('site_id', $sousMenu->site_id)->where('id', $data['menu_id'])->first();
            if (!$menu) {
                throw new UnprocessableEntityHttpException('Le menu parent n\'appartient pas à ce site.');
            }
        }

        return DB::transaction(function () use ($sousMenu, $data) {
            $sousMenu->update($data);
            return $sousMenu->fresh(['menu']);
        });
    }

    /**
     * Supprimer logiquement.
     */
    public function delete(SousMenu $sousMenu, ?string $userId = null): bool
    {
        if ($userId) {
            $sousMenu->deleted_by = $userId;
            $sousMenu->save();
        }
        return (bool) $sousMenu->delete();
    }

    /**
     * Restaurer un sous-menu.
     */
    public function restore(string $siteId, string $id): ?SousMenu
    {
        $sousMenu = SousMenu::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($sousMenu) {
            $sousMenu->restore();
            $sousMenu->deleted_by = null;
            $sousMenu->save();
            return $sousMenu->fresh(['menu']);
        }

        return null;
    }
}
