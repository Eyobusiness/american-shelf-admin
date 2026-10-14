<?php

namespace App\Services\Admin;

use App\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SiteService
{
    /**
     * Obtenir la liste paginée des sites avec recherche et filtres.
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Site::query();

        // Recherche par mot-clé (code, nom, email, telephone)
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'LIKE', $search)
                  ->orWhere('code', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search)
                  ->orWhere('telephone', 'LIKE', $search);
            });
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'code', 'nom', 'statut', 'created_at', 'date_creation'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'id';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un site par ID.
     */
    public function findById(string $id, bool $withTrashed = false): ?Site
    {
        $query = $withTrashed ? Site::withTrashed() : Site::query();
        return $query->find($id);
    }

    /**
     * Créer un nouveau site.
     */
    public function create(array $data): Site
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $data['logo'] = $data['logo']->store('logos', 'public');
            }

            return Site::create($data);
        });
    }

    /**
     * Mettre à jour un site existant.
     */
    public function update(Site $site, array $data): Site
    {
        return DB::transaction(function () use ($site, $data) {
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                if ($site->logo && !str_starts_with($site->logo, 'http') && Storage::disk('public')->exists($site->logo)) {
                    Storage::disk('public')->delete($site->logo);
                }
                $data['logo'] = $data['logo']->store('logos', 'public');
            }

            $site->update($data);
            return $site->fresh();
        });
    }

    /**
     * Mettre à jour spécifiquement le logo d'un site.
     */
    public function updateLogo(Site $site, UploadedFile $file): Site
    {
        return DB::transaction(function () use ($site, $file) {
            if ($site->logo && !str_starts_with($site->logo, 'http') && Storage::disk('public')->exists($site->logo)) {
                Storage::disk('public')->delete($site->logo);
            }

            $path = $file->store('logos', 'public');
            $site->update(['logo' => $path]);

            return $site->fresh();
        });
    }

    /**
     * Supprimer logiquement un site (Soft Delete).
     */
    public function delete(Site $site, ?string $userId = null): bool
    {
        if ($userId) {
            $site->deleted_by = $userId;
            $site->save();
        }
        return (bool) $site->delete();
    }

    /**
     * Restaurer un site supprimé.
     */
    public function restore(string $id): ?Site
    {
        $site = Site::onlyTrashed()->find($id);
        if ($site) {
            $site->restore();
            $site->deleted_by = null;
            $site->save();
            return $site;
        }
        return null;
    }
}
