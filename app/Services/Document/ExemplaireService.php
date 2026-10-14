<?php

namespace App\Services\Document;

use App\Models\Exemplaire;
use App\Models\Livre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExemplaireService
{
    public function __construct(
        protected ?ExemplaireMouvementService $mouvementService = null
    ) {
        $this->mouvementService = $mouvementService ?? new ExemplaireMouvementService();
    }

    /**
     * Récupère la liste paginée des exemplaires d'un site avec filtres et recherche.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Exemplaire::with(['livre.categorieLivre'])
            ->where('site_id', $siteId);

        // Recherche multi-critères (code exemplaire, localisation, ou données du livre parent)
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', $search)
                  ->orWhere('localisation', 'LIKE', $search)
                  ->orWhereHas('livre', function ($livreQuery) use ($search) {
                      $livreQuery->where('titre', 'LIKE', $search)
                                 ->orWhere('auteur', 'LIKE', $search)
                                 ->orWhere('code', 'LIKE', $search)
                                 ->orWhere('isbn', 'LIKE', $search);
                  });
            });
        }

        // Filtre par livre spécifique
        if (!empty($filters['livre_id'])) {
            $query->where('livre_id', $filters['livre_id']);
        }

        // Filtre par état (NEUF, BON, MOYEN, DEGRADE, PERDU)
        if (!empty($filters['etat'])) {
            $query->where('etat', $filters['etat']);
        }

        // Filtre par statut (DISPONIBLE, EMPRUNTE, EN_CONSULTATION, RESERVE, INDISPONIBLE)
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par localisation
        if (!empty($filters['localisation'])) {
            $query->where('localisation', 'LIKE', '%' . $filters['localisation'] . '%');
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'code', 'etat', 'statut', 'localisation', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un exemplaire par ID avec cloisonnement strict par site.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Exemplaire
    {
        $query = $withTrashed ? Exemplaire::withTrashed() : Exemplaire::query();

        return $query->with(['livre.categorieLivre'])
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Création unitaire d'un exemplaire avec génération automatique ou explicite de code.
     */
    public function create(string $siteId, array $data): Exemplaire
    {
        return DB::transaction(function () use ($siteId, $data) {
            // Verrouillage de la ligne du livre parent pour sérialiser la génération des codes
            $livre = Livre::where('site_id', $siteId)
                ->where('id', $data['livre_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (empty($data['code'])) {
                $data['code'] = $this->generateNextCode($siteId, $livre);
            }

            $data['site_id'] = $siteId;
            $data['etat'] = $data['etat'] ?? 'BON';
            $data['statut'] = $data['statut'] ?? 'DISPONIBLE';

            $exemplaire = Exemplaire::create($data);

            // Traçabilité de l'inventaire : mouvement initial
            $this->mouvementService->log(
                $exemplaire,
                $exemplaire->statut,
                $exemplaire->etat,
                null,
                null,
                'Création initiale de l\'exemplaire'
            );

            return $exemplaire->load('livre');
        });
    }

    /**
     * Création groupée (bulk) d'exemplaires avec génération séquentielle garantie et transaction atomique.
     *
     * @return Collection<int, Exemplaire>
     */
    public function createBulk(string $siteId, string $livreId, int $nombre, array $attributes = []): Collection
    {
        return DB::transaction(function () use ($siteId, $livreId, $nombre, $attributes) {
            // Verrouille la ligne du livre parent pour bloquer toute exécution concurrente sur ce livre
            $livre = Livre::where('site_id', $siteId)
                ->where('id', $livreId)
                ->lockForUpdate()
                ->firstOrFail();

            $created = new Collection();
            $currentSeq = $this->getMaxSequenceNumber($siteId, $livre);

            for ($i = 1; $i <= $nombre; $i++) {
                $currentSeq++;

                // Résistance absolue aux collisions : boucle jusqu'à trouver un code non existant (même soft-deleted)
                do {
                    $code = sprintf('%s-%03d', $livre->code, $currentSeq);
                    $exists = Exemplaire::withTrashed()
                        ->where('site_id', $siteId)
                        ->where('code', $code)
                        ->exists();

                    if (!$exists) {
                        break;
                    }
                    $currentSeq++;
                } while (true);

                $exemplaire = Exemplaire::create([
                    'site_id' => $siteId,
                    'livre_id' => $livre->id,
                    'code' => $code,
                    'etat' => $attributes['etat'] ?? 'BON',
                    'statut' => $attributes['statut'] ?? 'DISPONIBLE',
                    'localisation' => $attributes['localisation'] ?? null,
                ]);

                // Traçabilité de l'inventaire : mouvement initial
                $this->mouvementService->log(
                    $exemplaire,
                    $exemplaire->statut,
                    $exemplaire->etat,
                    null,
                    null,
                    'Création groupée d\'exemplaires'
                );

                $created->push($exemplaire);
            }

            return $created->load('livre');
        });
    }

    /**
     * Met à jour un exemplaire existant.
     */
    public function update(Exemplaire $exemplaire, array $data): Exemplaire
    {
        return DB::transaction(function () use ($exemplaire, $data) {
            $ancienStatut = $exemplaire->statut;
            $ancienEtat = $exemplaire->etat;

            $exemplaire->update($data);

            // Tracer le mouvement si l'état ou le statut a évolué
            if (($data['statut'] ?? null) && $data['statut'] !== $ancienStatut || ($data['etat'] ?? null) && $data['etat'] !== $ancienEtat) {
                $this->mouvementService->log(
                    $exemplaire,
                    $exemplaire->statut,
                    $exemplaire->etat,
                    $ancienStatut,
                    $ancienEtat,
                    $data['motif_changement'] ?? 'Mise à jour des informations de l\'exemplaire'
                );
            }

            return $exemplaire->fresh(['livre']);
        });
    }

    /**
     * Suppression logique d'un exemplaire avec horodatage et traçabilité d'audit.
     */
    public function delete(Exemplaire $exemplaire, ?string $userId = null): bool
    {
        if ($userId) {
            $exemplaire->deleted_by = $userId;
            $exemplaire->save();
        }

        return (bool) $exemplaire->delete();
    }

    /**
     * Restaure un exemplaire supprimé logiquement.
     */
    public function restore(string $siteId, string $id): ?Exemplaire
    {
        $exemplaire = Exemplaire::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($exemplaire) {
            $exemplaire->restore();
            $exemplaire->deleted_by = null;
            $exemplaire->save();
            return $exemplaire->load('livre');
        }

        return null;
    }

    /**
     * Calcule le plus grand numéro de séquence utilisé pour un livre donné (y compris soft-deleted).
     */
    protected function getMaxSequenceNumber(string $siteId, Livre $livre): int
    {
        $prefix = $livre->code . '-';

        $codes = Exemplaire::withTrashed()
            ->where('site_id', $siteId)
            ->where('livre_id', $livre->id)
            ->lockForUpdate()
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (str_starts_with($code, $prefix)) {
                $suffix = substr($code, strlen($prefix));
                if (ctype_digit($suffix)) {
                    $num = (int) $suffix;
                    if ($num > $max) {
                        $max = $num;
                    }
                }
            }
        }

        return $max;
    }

    /**
     * Génère le prochain code unique pour un livre donné.
     */
    protected function generateNextCode(string $siteId, Livre $livre): string
    {
        $seq = $this->getMaxSequenceNumber($siteId, $livre) + 1;

        do {
            $candidateCode = sprintf('%s-%03d', $livre->code, $seq);
            $exists = Exemplaire::withTrashed()
                ->where('site_id', $siteId)
                ->where('code', $candidateCode)
                ->exists();

            if (!$exists) {
                return $candidateCode;
            }
            $seq++;
        } while (true);
    }
}
