<?php

namespace App\Services\Usager;

use App\Models\Usager;
use App\Models\UsagerSite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsagerService
{
    /**
     * Liste paginée des usagers rattachés au site courant, avec filtres et recherche.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Usager::whereHas('usagerSites', function ($q) use ($siteId, $filters) {
            $q->where('site_id', $siteId);

            if (!empty($filters['type'])) {
                $q->where('type', $filters['type']);
            }

            if (!empty($filters['statut'])) {
                $q->where('statut', $filters['statut']);
            }

            if (!empty($filters['categorie_usager_id'])) {
                $q->where('categorie_usager_id', $filters['categorie_usager_id']);
            }
        })->with(['usagerSites' => function ($q) use ($siteId) {
            $q->where('site_id', $siteId)->with('categorieUsager');
        }]);

        // Recherche multi-colonnes
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search, $siteId) {
                $q->where('numero', 'LIKE', $search)
                  ->orWhere('nom', 'LIKE', $search)
                  ->orWhere('prenom', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search)
                  ->orWhere('contact', 'LIKE', $search)
                  ->orWhereHas('usagerSites', function ($usQuery) use ($search, $siteId) {
                      $usQuery->where('site_id', $siteId)->where('numero', 'LIKE', $search);
                  });
            });
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'numero', 'nom', 'prenom', 'email', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un usager par ID en s'assurant de son rattachement au site courant (Anti-IDOR).
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Usager
    {
        $query = $withTrashed ? Usager::withTrashed() : Usager::query();

        return $query->where('id', $id)
            ->whereHas('usagerSites', function ($q) use ($siteId, $withTrashed) {
                if ($withTrashed) {
                    $q->withTrashed();
                }
                $q->where('site_id', $siteId);
            })
            ->with(['usagerSites' => function ($q) use ($siteId, $withTrashed) {
                if ($withTrashed) {
                    $q->withTrashed();
                }
                $q->where('site_id', $siteId)->with('categorieUsager');
            }])
            ->first();
    }

    /**
     * Enregistrer un nouvel usager et créer son adhésion au site courant (ou lier une personne existante).
     */
    public function create(string $siteId, array $data): Usager
    {
        return DB::transaction(function () use ($siteId, $data) {
            $usager = null;

            // Si un email est renseigné, vérifier si un usager global existe déjà
            if (!empty($data['email'])) {
                $usager = Usager::where('email', $data['email'])->first();
            }

            // Si la personne n'existe pas encore globalement, on la crée
            if (!$usager) {
                $numero = $data['numero'] ?? ('USG-' . date('Y') . '-' . strtoupper(Str::random(6)));

                $usagerData = [
                    'numero' => $numero,
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'email' => $data['email'] ?? null,
                    'contact' => $data['contact'] ?? null,
                ];

                if (!empty($data['mot_de_passe'])) {
                    $usagerData['mot_de_passe'] = Hash::make($data['mot_de_passe']);
                }

                $usager = Usager::create($usagerData);
            }

            // Créer ou restaurer le rattachement UsagerSite pour le site courant
            $membership = UsagerSite::withTrashed()
                ->where('site_id', $siteId)
                ->where('usager_id', $usager->id)
                ->first();

            if ($membership) {
                if ($membership->trashed()) {
                    $membership->restore();
                }
                $membership->update([
                    'numero' => $data['numero_local'] ?? $membership->numero,
                    'date_inscription' => $data['date_inscription'] ?? $membership->date_inscription ?? now()->toDateString(),
                    'type' => $data['type'] ?? $membership->type ?? 'USAGER',
                    'categorie_usager_id' => $data['categorie_usager_id'] ?? $membership->categorie_usager_id,
                    'statut' => $data['statut'] ?? $membership->statut ?? 'ACTIF',
                    'deleted_by' => null,
                ]);
            } else {
                UsagerSite::create([
                    'site_id' => $siteId,
                    'usager_id' => $usager->id,
                    'numero' => $data['numero_local'] ?? null,
                    'date_inscription' => $data['date_inscription'] ?? now()->toDateString(),
                    'type' => $data['type'] ?? 'USAGER',
                    'categorie_usager_id' => $data['categorie_usager_id'] ?? null,
                    'statut' => $data['statut'] ?? 'ACTIF',
                ]);
            }

            return $usager->load(['usagerSites' => function ($q) use ($siteId) {
                $q->where('site_id', $siteId)->with('categorieUsager');
            }]);
        });
    }

    /**
     * Mettre à jour les informations d'un usager et son contexte d'adhésion au site.
     */
    public function update(string $siteId, Usager $usager, array $data): Usager
    {
        return DB::transaction(function () use ($siteId, $usager, $data) {
            $usagerFields = array_intersect_key($data, array_flip(['nom', 'prenom', 'email', 'contact', 'numero']));

            if (!empty($data['mot_de_passe'])) {
                $usagerFields['mot_de_passe'] = Hash::make($data['mot_de_passe']);
            }

            if (!empty($usagerFields)) {
                $usager->update($usagerFields);
            }

            // Mettre à jour le contexte UsagerSite pour ce site
            $membership = UsagerSite::where('site_id', $siteId)
                ->where('usager_id', $usager->id)
                ->first();

            if ($membership) {
                $membershipData = [];
                if (isset($data['type'])) $membershipData['type'] = $data['type'];
                if (array_key_exists('categorie_usager_id', $data)) $membershipData['categorie_usager_id'] = $data['categorie_usager_id'];
                if (isset($data['numero_local'])) $membershipData['numero'] = $data['numero_local'];
                if (isset($data['date_inscription'])) $membershipData['date_inscription'] = $data['date_inscription'];
                if (isset($data['statut'])) $membershipData['statut'] = $data['statut'];

                if (!empty($membershipData)) {
                    $membership->update($membershipData);
                }
            }

            return $usager->fresh(['usagerSites' => function ($q) use ($siteId) {
                $q->where('site_id', $siteId)->with('categorieUsager');
            }]);
        });
    }

    /**
     * Détacher / supprimer logiquement un usager du site courant.
     */
    public function delete(string $siteId, Usager $usager, ?string $userId = null): bool
    {
        $membership = UsagerSite::where('site_id', $siteId)
            ->where('usager_id', $usager->id)
            ->first();

        if ($membership) {
            if ($userId) {
                $membership->deleted_by = $userId;
                $membership->save();
            }
            return (bool) $membership->delete();
        }

        return false;
    }

    /**
     * Restaurer le rattachement au site courant.
     */
    public function restore(string $siteId, string $id): ?Usager
    {
        $membership = UsagerSite::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('usager_id', $id)
            ->first();

        if ($membership) {
            $membership->restore();
            $membership->deleted_by = null;
            $membership->save();

            return Usager::with(['usagerSites' => function ($q) use ($siteId) {
                $q->where('site_id', $siteId)->with('categorieUsager');
            }])->find($id);
        }

        return null;
    }
}
