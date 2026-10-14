<?php

namespace App\Services\Usager;

use App\Models\Presence;
use App\Models\Usager;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PresenceService
{
    /**
     * Liste paginée des présences du site avec filtres et recherche.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Presence::with(['usager'])
            ->where('site_id', $siteId);

        // Filtre par date exacte
        if (!empty($filters['date_presence'])) {
            $query->whereDate('date_presence', $filters['date_presence']);
        }

        // Filtre par intervalle de dates
        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_presence', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_presence', '<=', $filters['date_fin']);
        }

        // Filtre par année
        if (!empty($filters['annee'])) {
            $query->whereYear('date_presence', (int) $filters['annee']);
        }

        // Filtre par mois
        if (!empty($filters['mois'])) {
            $query->whereMonth('date_presence', (int) $filters['mois']);
        }

        // Filtre par usager
        if (!empty($filters['usager_id'])) {
            $query->where('usager_id', $filters['usager_id']);
        }

        // Filtre par statut (EN_COURS vs TERMINE)
        if (!empty($filters['statut'])) {
            $statut = strtoupper(trim($filters['statut']));
            if ($statut === 'EN_COURS') {
                $query->whereNull('heure_depart');
            } elseif ($statut === 'TERMINE') {
                $query->whereNotNull('heure_depart');
            }
        }

        // Filtre présence anonyme vs identifiée
        if (isset($filters['anonyme']) && $filters['anonyme'] !== '') {
            $isAnonyme = filter_var($filters['anonyme'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isAnonyme === true) {
                $query->whereNull('usager_id');
            } elseif ($isAnonyme === false) {
                $query->whereNotNull('usager_id');
            }
        }

        // Filtre par motif
        if (!empty($filters['motif'])) {
            $query->where('motif', 'LIKE', '%' . $filters['motif'] . '%');
        }

        // Recherche multi-critères
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'LIKE', $search)
                  ->orWhere('observations', 'LIKE', $search)
                  ->orWhereHas('usager', function ($uQuery) use ($search) {
                      $uQuery->where('nom', 'LIKE', $search)
                             ->orWhere('prenom', 'LIKE', $search)
                             ->orWhere('email', 'LIKE', $search)
                             ->orWhere('numero', 'LIKE', $search);
                  });
            });
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'date_presence', 'heure_arrivee', 'heure_depart', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'date_presence';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)
            ->orderBy('heure_arrivee', $sortDirection)
            ->paginate($perPage);
    }

    /**
     * Trouver un enregistrement de présence au sein du site courant.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Presence
    {
        $query = $withTrashed ? Presence::withTrashed() : Presence::query();

        return $query->with(['usager'])
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Enregistrer une présence (identifiée ou anonyme).
     */
    public function create(string $siteId, array $data): Presence
    {
        $data['site_id'] = $siteId;
        $data['date_presence'] = $data['date_presence'] ?? now()->toDateString();

        // Contrôle anti-double pointage actif pour un même usager à la même date
        if (!empty($data['usager_id'])) {
            $activePresence = Presence::where('site_id', $siteId)
                ->where('usager_id', $data['usager_id'])
                ->whereDate('date_presence', $data['date_presence'])
                ->whereNull('heure_depart')
                ->first();

            if ($activePresence) {
                $arr = substr($activePresence->heure_arrivee, 0, 5);
                throw new InvalidArgumentException("Cet usager a déjà un pointage en cours non clôturé pour cette date (arrivée enregistrée à {$arr}). Veuillez enregistrer son départ avant d'initier un nouveau passage.");
            }
        }

        // Validation cohérence horaire si l'heure de départ est fournie dès l'arrivée
        if (!empty($data['heure_arrivee']) && !empty($data['heure_depart'])) {
            if ($data['heure_depart'] < $data['heure_arrivee']) {
                throw new InvalidArgumentException('L\'heure de départ doit être postérieure ou égale à l\'heure d\'arrivée.');
            }
        }

        return DB::transaction(function () use ($data) {
            $presence = Presence::create($data);
            return $presence->load('usager');
        });
    }

    /**
     * Mettre à jour une présence existante.
     */
    public function update(Presence $presence, array $data): Presence
    {
        $heureArrivee = $data['heure_arrivee'] ?? $presence->heure_arrivee;
        $heureDepart = array_key_exists('heure_depart', $data) ? $data['heure_depart'] : $presence->heure_depart;

        if (!empty($heureArrivee) && !empty($heureDepart)) {
            if ($heureDepart < $heureArrivee) {
                throw new InvalidArgumentException('L\'heure de départ doit être postérieure ou égale à l\'heure d\'arrivée.');
            }
        }

        return DB::transaction(function () use ($presence, $data) {
            $presence->update($data);
            return $presence->fresh(['usager']);
        });
    }

    /**
     * Enregistrer spécifiquement la sortie d'un usager pour sa présence en cours.
     */
    public function enregistrerSortie(Presence $presence, ?string $heureDepart = null, ?string $observations = null): Presence
    {
        if (!empty($presence->heure_depart)) {
            $dep = substr($presence->heure_depart, 0, 5);
            throw new InvalidArgumentException("La sortie de ce passage a déjà été enregistrée (à {$dep}).");
        }

        $heure = $heureDepart ? substr($heureDepart, 0, 5) : now()->format('H:i');
        $arr = substr($presence->heure_arrivee, 0, 5);

        if ($heure < $arr) {
            throw new InvalidArgumentException('L\'heure de départ doit être postérieure ou égale à l\'heure d\'arrivée.');
        }

        $updateData = [
            'heure_depart' => $heure,
        ];

        if ($observations !== null && trim($observations) !== '') {
            $existingObs = $presence->observations ? $presence->observations . ' | ' : '';
            $updateData['observations'] = $existingObs . trim($observations);
        }

        return $this->update($presence, $updateData);
    }

    /**
     * Obtenir les statistiques réelles de fréquentation pour le site et éventuellement un usager.
     */
    public function getStatistiques(string $siteId, array $filters = []): array
    {
        $targetYear = (int) ($filters['annee'] ?? now()->year);
        $targetMonth = (int) ($filters['mois'] ?? now()->month);
        $usagerId = !empty($filters['usager_id']) ? (string) $filters['usager_id'] : null;
        $today = now()->toDateString();

        $baseQuery = Presence::where('site_id', $siteId);

        if (!empty($usagerId)) {
            $baseQuery->where('usager_id', $usagerId);
        }

        if (!empty($filters['date_debut'])) {
            $baseQuery->whereDate('date_presence', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $baseQuery->whereDate('date_presence', '<=', $filters['date_fin']);
        }

        // Total général des passages
        $totalPassages = (clone $baseQuery)->count();

        // Passages du jour
        $passagesAujourdhui = Presence::where('site_id', $siteId)
            ->when(!empty($usagerId), fn($q) => $q->where('usager_id', $usagerId))
            ->whereDate('date_presence', $today)
            ->count();

        // En cours actuellement en salle (sans heure de départ aujourd'hui)
        $enCoursAujourdhui = Presence::where('site_id', $siteId)
            ->when(!empty($usagerId), fn($q) => $q->where('usager_id', $usagerId))
            ->whereDate('date_presence', $today)
            ->whereNull('heure_depart')
            ->count();

        // Passages de l'année sélectionnée
        $passagesAnnee = Presence::where('site_id', $siteId)
            ->when(!empty($usagerId), fn($q) => $q->where('usager_id', $usagerId))
            ->whereYear('date_presence', $targetYear)
            ->count();

        // Passages du mois sélectionné dans l'année sélectionnée
        $passagesMois = Presence::where('site_id', $siteId)
            ->when(!empty($usagerId), fn($q) => $q->where('usager_id', $usagerId))
            ->whereYear('date_presence', $targetYear)
            ->whereMonth('date_presence', $targetMonth)
            ->count();

        // Distribution mensuelle sur l'année sélectionnée (12 mois)
        $moisLabels = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $presencesAnnee = Presence::where('site_id', $siteId)
            ->when(!empty($usagerId), fn($q) => $q->where('usager_id', $usagerId))
            ->whereYear('date_presence', $targetYear)
            ->get(['date_presence', 'usager_id']);

        $mensuelleRaw = [];
        $anonymesAnnee = 0;
        $membresAnnee = 0;

        foreach ($presencesAnnee as $p) {
            $m = (int) Carbon::parse($p->date_presence)->month;
            $mensuelleRaw[$m] = ($mensuelleRaw[$m] ?? 0) + 1;

            if (empty($p->usager_id)) {
                $anonymesAnnee++;
            } else {
                $membresAnnee++;
            }
        }

        $frequentationMensuelle = [];
        for ($m = 1; $m <= 12; $m++) {
            $frequentationMensuelle[] = [
                'mois' => $m,
                'mois_nom' => $moisLabels[$m],
                'total' => (int) ($mensuelleRaw[$m] ?? 0),
            ];
        }

        // Informations usager si ciblé
        $usagerInfo = null;
        if (!empty($usagerId)) {
            $u = Usager::find($usagerId);
            if ($u) {
                $usagerInfo = [
                    'id' => $u->id,
                    'nom' => $u->nom,
                    'prenom' => $u->prenom,
                    'numero' => $u->numero,
                    'email' => $u->email,
                    'contact' => $u->contact,
                ];
            }
        }

        return [
            'annee_selectionnee' => $targetYear,
            'mois_selectionne' => $targetMonth,
            'mois_nom' => $moisLabels[$targetMonth] ?? '',
            'usager' => $usagerInfo,
            'bibliotheque' => [
                'total_passages' => $totalPassages,
                'passages_aujourdhui' => $passagesAujourdhui,
                'en_cours_aujourdhui' => $enCoursAujourdhui,
                'passages_mois' => $passagesMois,
                'passages_annee' => $passagesAnnee,
                'visiteurs_anonymes_annee' => $anonymesAnnee,
                'visiteurs_membres_annee' => $membresAnnee,
            ],
            'frequentation_mensuelle' => $frequentationMensuelle,
        ];
    }

    /**
     * Suppression logique d'une présence.
     */
    public function delete(Presence $presence, ?string $userId = null): bool
    {
        if ($userId) {
            $presence->deleted_by = $userId;
            $presence->save();
        }

        return (bool) $presence->delete();
    }

    /**
     * Restauration d'une présence supprimée.
     */
    public function restore(string $siteId, string $id): ?Presence
    {
        $presence = Presence::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($presence) {
            $presence->restore();
            $presence->deleted_by = null;
            $presence->save();
            return $presence->load('usager');
        }

        return null;
    }
}
