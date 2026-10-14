<?php

namespace App\Services\Admin;

use App\Models\JournalActivite;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalActiviteService
{
    /**
     * Liste paginée du journal d'activité pour un site donné.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = JournalActivite::with('utilisateur')
            ->where('site_id', $siteId);

        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', $search)
                  ->orWhere('reference', 'LIKE', $search)
                  ->orWhere('action', 'LIKE', $search)
                  ->orWhere('module', 'LIKE', $search)
                  ->orWhereHas('utilisateur', function ($uQuery) use ($search) {
                      $uQuery->where('nom', 'LIKE', $search)
                             ->orWhere('prenom', 'LIKE', $search)
                             ->orWhere('email', 'LIKE', $search);
                  });
            });
        }

        // Filtre par module
        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        // Filtre par action
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Filtre par utilisateur
        if (!empty($filters['utilisateur_id'])) {
            $query->where('utilisateur_id', $filters['utilisateur_id']);
        }

        // Filtre par date exacte
        if (!empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        // Filtre par période
        if (!empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'created_at', 'action', 'module'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $order = in_array(strtolower($filters['sort_direction'] ?? $filters['order'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_direction'] ?? $filters['order'] ?? '')
            : 'desc';

        return $query->orderBy($sortBy, $order)->paginate($perPage);
    }

    /**
     * Trouver une entrée de journal par ID dans le tenant.
     */
    public function findById(string $siteId, string $id): ?JournalActivite
    {
        return JournalActivite::with('utilisateur')
            ->where('site_id', $siteId)
            ->find($id);
    }

    /**
     * Enregistrer une nouvelle activité dans le journal (immutable).
     */
    public function log(
        string $siteId,
        ?string $utilisateurId,
        string $action,
        string $module,
        ?string $reference = null,
        ?string $description = null,
        ?string $ip = null
    ): JournalActivite {
        return JournalActivite::create([
            'site_id' => $siteId,
            'utilisateur_id' => $utilisateurId,
            'action' => $action,
            'module' => $module,
            'reference' => $reference,
            'description' => $description,
            'adresse_ip' => $ip,
            'created_at' => now(),
        ]);
    }
}
