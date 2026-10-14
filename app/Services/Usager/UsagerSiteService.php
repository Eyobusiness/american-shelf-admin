<?php

namespace App\Services\Usager;

use App\Models\UsagerSite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UsagerSiteService
{
    /**
     * Liste paginée des adhésions (UsagerSite) pour le site courant.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = UsagerSite::with(['usager', 'categorieUsager'])
            ->where('site_id', $siteId);

        // Filtre par type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par catégorie
        if (!empty($filters['categorie_usager_id'])) {
            $query->where('categorie_usager_id', $filters['categorie_usager_id']);
        }

        // Recherche (nom, prénom, email, contact, numéro usager ou numéro local)
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'LIKE', $search)
                  ->orWhereHas('usager', function ($uQuery) use ($search) {
                      $uQuery->where('nom', 'LIKE', $search)
                             ->orWhere('prenom', 'LIKE', $search)
                             ->orWhere('email', 'LIKE', $search)
                             ->orWhere('contact', 'LIKE', $search)
                             ->orWhere('numero', 'LIKE', $search);
                  });
            });
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'numero', 'date_inscription', 'type', 'statut', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'date_inscription';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un enregistrement UsagerSite par ID pour le site courant.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?UsagerSite
    {
        $query = $withTrashed ? UsagerSite::withTrashed() : UsagerSite::query();

        return $query->with(['usager', 'categorieUsager'])
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Créer une adhésion pour un usager existant au site courant.
     */
    public function create(string $siteId, array $data): UsagerSite
    {
        $data['site_id'] = $siteId;
        $data['date_inscription'] = $data['date_inscription'] ?? now()->toDateString();
        $data['type'] = $data['type'] ?? 'USAGER';
        $data['statut'] = $data['statut'] ?? 'ACTIF';

        return DB::transaction(function () use ($siteId, $data) {
            $existing = UsagerSite::withTrashed()
                ->where('site_id', $siteId)
                ->where('usager_id', $data['usager_id'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($data);
                $existing->deleted_by = null;
                $existing->save();
                return $existing->fresh(['usager', 'categorieUsager']);
            }

            $usagerSite = UsagerSite::create($data);
            return $usagerSite->load(['usager', 'categorieUsager']);
        });
    }

    /**
     * Mettre à jour une adhésion UsagerSite.
     */
    public function update(UsagerSite $usagerSite, array $data): UsagerSite
    {
        return DB::transaction(function () use ($usagerSite, $data) {
            $usagerSite->update($data);
            return $usagerSite->fresh(['usager', 'categorieUsager']);
        });
    }

    /**
     * Suppression logique d'une adhésion UsagerSite.
     */
    public function delete(UsagerSite $usagerSite, ?string $userId = null): bool
    {
        if ($userId) {
            $usagerSite->deleted_by = $userId;
            $usagerSite->save();
        }

        return (bool) $usagerSite->delete();
    }

    /**
     * Restaurer une adhésion supprimée logiquement.
     */
    public function restore(string $siteId, string $id): ?UsagerSite
    {
        $usagerSite = UsagerSite::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($usagerSite) {
            $usagerSite->restore();
            $usagerSite->deleted_by = null;
            $usagerSite->save();
            return $usagerSite->load(['usager', 'categorieUsager']);
        }

        return null;
    }
}
