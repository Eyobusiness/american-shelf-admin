<?php

namespace App\Services\Admin;

use App\Models\Sauvegarde;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SauvegardeService
{
    /**
     * Liste paginée des sauvegardes pour un site donné.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Sauvegarde::where('site_id', $siteId);

        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nom_fichier', 'LIKE', $search)
                  ->orWhere('chemin_fichier', 'LIKE', $search)
                  ->orWhere('statut', 'LIKE', $search);
            });
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par date de sauvegarde
        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_sauvegarde', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_sauvegarde', '<=', $filters['date_fin']);
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'date_sauvegarde', 'created_at', 'nom_fichier', 'taille', 'statut'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'date_sauvegarde';
        $order = in_array(strtolower($filters['sort_direction'] ?? $filters['order'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_direction'] ?? $filters['order'] ?? '')
            : 'desc';

        return $query->orderBy($sortBy, $order)->paginate($perPage);
    }

    /**
     * Trouver une sauvegarde par son ID au sein du site courant.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Sauvegarde
    {
        $query = Sauvegarde::where('site_id', $siteId);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /**
     * Enregistrer une métadonnée de sauvegarde.
     */
    public function create(string $siteId, array $data): Sauvegarde
    {
        return DB::transaction(function () use ($siteId, $data) {
            return Sauvegarde::create([
                'site_id' => $siteId,
                'nom_fichier' => $data['nom_fichier'],
                'chemin_fichier' => $data['chemin_fichier'],
                'taille' => (int) ($data['taille'] ?? 0),
                'date_sauvegarde' => !empty($data['date_sauvegarde'])
                    ? Carbon::parse($data['date_sauvegarde'])
                    : now(),
                'statut' => $data['statut'] ?? 'TERMINE',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Suppression logique d'une sauvegarde.
     */
    public function delete(Sauvegarde $sauvegarde, ?string $userId = null): bool
    {
        return DB::transaction(function () use ($sauvegarde, $userId) {
            if ($userId) {
                $sauvegarde->deleted_by = $userId;
                $sauvegarde->save();
            }

            return (bool) $sauvegarde->delete();
        });
    }

    /**
     * Restauration d'une sauvegarde supprimée logiquement.
     */
    public function restore(string $siteId, string $id): ?Sauvegarde
    {
        $sauvegarde = Sauvegarde::onlyTrashed()
            ->where('site_id', $siteId)
            ->find($id);

        if (!$sauvegarde) {
            return null;
        }

        $sauvegarde->restore();
        $sauvegarde->deleted_by = null;
        $sauvegarde->save();

        return $sauvegarde;
    }
}
