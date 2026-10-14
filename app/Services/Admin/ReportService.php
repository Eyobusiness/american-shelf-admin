<?php

namespace App\Services\Admin;

use App\Models\Acquisition;
use App\Models\CategorieLivre;
use App\Models\Exemplaire;
use App\Models\ExemplaireMouvement;
use App\Models\InventaireSnapshot;
use App\Models\Livre;
use App\Models\Operation;
use App\Models\Site;
use App\Services\Usager\PresenceService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        protected PresenceService $presenceService
    ) {}

    /**
     * Résoudre les bornes temporelles d'une période selon les filtres.
     */
    public function resolvePeriod(array $filters): array
    {
        $now = now();

        if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
            $startDate = Carbon::parse($filters['date_debut'])->startOfDay();
            $endDate = Carbon::parse($filters['date_fin'])->endOfDay();
            $type = 'personnalisee';
        } elseif (!empty($filters['date_debut'])) {
            $startDate = Carbon::parse($filters['date_debut'])->startOfDay();
            $endDate = $now->copy()->endOfDay();
            $type = 'depuis_date';
        } elseif (!empty($filters['mois']) && !empty($filters['annee'])) {
            $year = (int) $filters['annee'];
            $month = (int) $filters['mois'];
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();
            $type = 'mensuelle';
        } elseif (!empty($filters['mois'])) {
            $year = $now->year;
            $month = (int) $filters['mois'];
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();
            $type = 'mensuelle';
        } elseif (!empty($filters['annee'])) {
            $year = (int) $filters['annee'];
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfDay();
            $type = 'annuelle';
        } else {
            // Par défaut : l'année en cours
            $startDate = Carbon::createFromDate($now->year, 1, 1)->startOfDay();
            $endDate = $now->copy()->endOfDay();
            $type = 'annee_en_cours';
        }

        return [
            'date_debut' => $startDate->toDateString(),
            'date_fin' => $endDate->toDateString(),
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'type_periode' => $type,
        ];
    }

    /**
     * 1. Rapport du fonds documentaire (Stock physique et références).
     */
    public function getFondsDocumentaireReport(string $siteId, array $filters = []): array
    {
        $site = Site::find($siteId);

        // Requête de base pour les exemplaires
        $exemplairesBase = Exemplaire::where('site_id', $siteId);

        // Filtre par catégorie de livre si demandé
        if (!empty($filters['categorie_livre_id'])) {
            $catId = $filters['categorie_livre_id'];
            $exemplairesBase->whereHas('livre', fn($q) => $q->where('categorie_livre_id', $catId));
        }

        $totalExemplaires = (clone $exemplairesBase)->count();
        $disponibles = (clone $exemplairesBase)->where('statut', 'DISPONIBLE')->where('etat', '!=', 'PERDU')->count();
        $empruntes = (clone $exemplairesBase)->where('statut', 'EMPRUNTE')->count();
        $enConsultation = (clone $exemplairesBase)->where('statut', 'EN_CONSULTATION')->count();
        $reserves = (clone $exemplairesBase)->where('statut', 'RESERVE')->count();
        $indisponibles = (clone $exemplairesBase)->where('statut', 'INDISPONIBLE')->where('etat', '!=', 'PERDU')->count();
        $perdus = (clone $exemplairesBase)->where('etat', 'PERDU')->count();

        // Références bibliographiques présentes sur ce site
        $livresQuery = Livre::whereHas('exemplaires', fn($q) => $q->where('site_id', $siteId));
        if (!empty($filters['categorie_livre_id'])) {
            $livresQuery->where('categorie_livre_id', $filters['categorie_livre_id']);
        }
        $totalLivres = (clone $livresQuery)->count();

        // Répartition par état physique
        $etatsRaw = Exemplaire::where('site_id', $siteId)
            ->when(!empty($filters['categorie_livre_id']), fn($q) => $q->whereHas('livre', fn($lq) => $lq->where('categorie_livre_id', $filters['categorie_livre_id'])))
            ->select('etat', DB::raw('COUNT(*) as total'))
            ->groupBy('etat')
            ->pluck('total', 'etat')
            ->toArray();

        $repartitionEtats = [
            'NEUF' => (int) ($etatsRaw['NEUF'] ?? 0),
            'BON' => (int) ($etatsRaw['BON'] ?? 0),
            'MOYEN' => (int) ($etatsRaw['MOYEN'] ?? 0),
            'DEGRADE' => (int) ($etatsRaw['DEGRADE'] ?? 0),
            'PERDU' => (int) ($etatsRaw['PERDU'] ?? 0),
        ];

        // Répartition par statut opérationnel
        $statutsRaw = Exemplaire::where('site_id', $siteId)
            ->when(!empty($filters['categorie_livre_id']), fn($q) => $q->whereHas('livre', fn($lq) => $lq->where('categorie_livre_id', $filters['categorie_livre_id'])))
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $repartitionStatuts = [
            'DISPONIBLE' => (int) ($statutsRaw['DISPONIBLE'] ?? 0),
            'EMPRUNTE' => (int) ($statutsRaw['EMPRUNTE'] ?? 0),
            'EN_CONSULTATION' => (int) ($statutsRaw['EN_CONSULTATION'] ?? 0),
            'RESERVE' => (int) ($statutsRaw['RESERVE'] ?? 0),
            'INDISPONIBLE' => (int) ($statutsRaw['INDISPONIBLE'] ?? 0),
        ];

        // Répartition par catégorie
        $categoriesStats = DB::table('exemplaires')
            ->join('livres', 'exemplaires.livre_id', '=', 'livres.id')
            ->leftJoin('categories_livres', 'livres.categorie_livre_id', '=', 'categories_livres.id')
            ->where('exemplaires.site_id', $siteId)
            ->whereNull('exemplaires.deleted_at')
            ->whereNull('livres.deleted_at')
            ->select(
                DB::raw("COALESCE(categories_livres.id, 'sans_categorie') as categorie_id"),
                DB::raw("COALESCE(categories_livres.nom, 'Sans catégorie') as categorie_libelle"),
                DB::raw("COUNT(DISTINCT livres.id) as total_livres"),
                DB::raw("COUNT(exemplaires.id) as total_exemplaires"),
                DB::raw("SUM(CASE WHEN exemplaires.statut = 'DISPONIBLE' AND exemplaires.etat != 'PERDU' THEN 1 ELSE 0 END) as exemplaires_disponibles"),
                DB::raw("SUM(CASE WHEN exemplaires.statut = 'EMPRUNTE' THEN 1 ELSE 0 END) as exemplaires_empruntes"),
                DB::raw("SUM(CASE WHEN exemplaires.etat = 'PERDU' THEN 1 ELSE 0 END) as exemplaires_perdus")
            )
            ->groupBy('categories_livres.id', 'categories_livres.nom')
            ->orderByDesc('total_exemplaires')
            ->get()
            ->map(fn($item) => [
                'categorie_id' => $item->categorie_id,
                'categorie' => $item->categorie_libelle,
                'total_livres' => (int) $item->total_livres,
                'total_exemplaires' => (int) $item->total_exemplaires,
                'disponibles' => (int) $item->exemplaires_disponibles,
                'empruntes' => (int) $item->exemplaires_empruntes,
                'perdus' => (int) $item->exemplaires_perdus,
            ]);

        // Détail des livres paginé avec compteurs optimisés
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $livresDetailsQuery = Livre::whereHas('exemplaires', fn($q) => $q->where('site_id', $siteId))
            ->with(['categorieLivre:id,nom'])
            ->withCount([
                'exemplaires as total_exemplaires' => fn($q) => $q->where('site_id', $siteId),
                'exemplaires as exemplaires_disponibles' => fn($q) => $q->where('site_id', $siteId)->where('statut', 'DISPONIBLE')->where('etat', '!=', 'PERDU'),
                'exemplaires as exemplaires_empruntes' => fn($q) => $q->where('site_id', $siteId)->where('statut', 'EMPRUNTE'),
                'exemplaires as exemplaires_en_consultation' => fn($q) => $q->where('site_id', $siteId)->where('statut', 'EN_CONSULTATION'),
                'exemplaires as exemplaires_perdus' => fn($q) => $q->where('site_id', $siteId)->where('etat', 'PERDU'),
            ]);

        if (!empty($filters['categorie_livre_id'])) {
            $livresDetailsQuery->where('categorie_livre_id', $filters['categorie_livre_id']);
        }

        if (!empty($filters['livre_id'])) {
            $livresDetailsQuery->where('id', $filters['livre_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $livresDetailsQuery->where(function ($q) use ($search) {
                $q->where('titre', 'LIKE', $search)
                  ->orWhere('auteur', 'LIKE', $search)
                  ->orWhere('code', 'LIKE', $search)
                  ->orWhere('isbn', 'LIKE', $search);
            });
        }

        $livresPaginator = $livresDetailsQuery->orderBy('titre')->paginate($perPage);

        $livresItems = collect($livresPaginator->items())->map(function ($livre) {
            return [
                'id' => $livre->id,
                'code' => $livre->code,
                'titre' => $livre->titre,
                'auteur' => $livre->auteur,
                'isbn' => $livre->isbn,
                'categorie' => $livre->categorieLivre?->nom ?? 'Non catégorisé',
                'total_exemplaires' => (int) $livre->total_exemplaires,
                'disponibles' => (int) $livre->exemplaires_disponibles,
                'empruntes' => (int) $livre->exemplaires_empruntes,
                'en_consultation' => (int) $livre->exemplaires_en_consultation,
                'perdus' => (int) $livre->exemplaires_perdus,
            ];
        });

        return [
            'site' => [
                'id' => $site?->id,
                'nom' => $site?->nom,
                'code' => $site?->code,
            ],
            'date_generation' => now()->toIso8601String(),
            'synthese' => [
                'total_references_bibliographiques' => $totalLivres,
                'total_exemplaires' => $totalExemplaires,
                'exemplaires_disponibles' => $disponibles,
                'exemplaires_empruntes' => $empruntes,
                'exemplaires_en_consultation' => $enConsultation,
                'exemplaires_reserves' => $reserves,
                'exemplaires_indisponibles' => $indisponibles,
                'exemplaires_perdus' => $perdus,
            ],
            'repartition_par_etat' => $repartitionEtats,
            'repartition_par_statut' => $repartitionStatuts,
            'repartition_par_categorie' => $categoriesStats,
            'livres' => [
                'data' => $livresItems,
                'pagination' => [
                    'current_page' => $livresPaginator->currentPage(),
                    'last_page' => $livresPaginator->lastPage(),
                    'per_page' => $livresPaginator->perPage(),
                    'total' => $livresPaginator->total(),
                ],
            ],
        ];
    }

    /**
     * 2. Rapport des emprunts, retours et consultations sur période.
     */
    public function getEmpruntsReport(string $siteId, array $filters = []): array
    {
        $site = Site::find($siteId);
        $period = $this->resolvePeriod($filters);
        $startDate = $period['start_datetime'];
        $endDate = $period['end_datetime'];

        $baseOperationQuery = fn($type) => Operation::where('site_id', $siteId)
            ->where('type', $type)
            ->when(!empty($filters['categorie_livre_id']), function ($q) use ($filters) {
                $q->whereHas('exemplaire.livre', fn($lq) => $lq->where('categorie_livre_id', $filters['categorie_livre_id']));
            });

        // 1. Emprunts démarrés pendant la période
        $empruntsPeriode = $baseOperationQuery('EMPRUNT')
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->count();

        // 2. Retours réalisés pendant la période (quelle que soit la date d'emprunt initiale)
        $retoursPeriode = $baseOperationQuery('EMPRUNT')
            ->whereNotNull('date_retour_effective')
            ->whereBetween('date_retour_effective', [$startDate, $endDate])
            ->count();

        // 3. Emprunts actuellement en cours (non retournés)
        $empruntsEnCours = $baseOperationQuery('EMPRUNT')
            ->whereNull('date_retour_effective')
            ->whereIn('statut', ['EN_COURS', 'EN_RETARD'])
            ->count();

        // 4. Emprunts en retard actifs (avec respect des jours de grâce)
        $empruntsEnRetard = Operation::with('site.configuration')
            ->where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->whereNull('date_retour_effective')
            ->whereNotNull('date_retour_prevue')
            ->when(!empty($filters['categorie_livre_id']), function ($q) use ($filters) {
                $q->whereHas('exemplaire.livre', fn($lq) => $lq->where('categorie_livre_id', $filters['categorie_livre_id']));
            })
            ->get()
            ->filter(function ($op) {
                $graceDays = (int) ($op->site?->configuration?->nombre_jours_grace ?? 0);
                return Carbon::parse($op->date_retour_prevue)->addDays($graceDays)->isPast();
            })
            ->count();

        // 5. Consultations sur place sur la période
        $consultationsPeriode = $baseOperationQuery('CONSULTATION')
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->count();

        // 6. Évolution mensuelle sur la période
        $monthlyEmpruntsRaw = DB::table('operations')
            ->where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->select(
                DB::raw("DATE_FORMAT(date_operation, '%Y-%m') as mois_cle"),
                DB::raw("COUNT(*) as total_emprunts")
            )
            ->groupBy('mois_cle')
            ->pluck('total_emprunts', 'mois_cle')
            ->toArray();

        $monthlyRetoursRaw = DB::table('operations')
            ->where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->whereNotNull('date_retour_effective')
            ->whereBetween('date_retour_effective', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->select(
                DB::raw("DATE_FORMAT(date_retour_effective, '%Y-%m') as mois_cle"),
                DB::raw("COUNT(*) as total_retours")
            )
            ->groupBy('mois_cle')
            ->pluck('total_retours', 'mois_cle')
            ->toArray();

        $monthlyConsultationsRaw = DB::table('operations')
            ->where('site_id', $siteId)
            ->where('type', 'CONSULTATION')
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->select(
                DB::raw("DATE_FORMAT(date_operation, '%Y-%m') as mois_cle"),
                DB::raw("COUNT(*) as total_consultations")
            )
            ->groupBy('mois_cle')
            ->pluck('total_consultations', 'mois_cle')
            ->toArray();

        // Union des clés de mois pour une timeline ordonnée
        $allMonthKeys = array_unique(array_merge(
            array_keys($monthlyEmpruntsRaw),
            array_keys($monthlyRetoursRaw),
            array_keys($monthlyConsultationsRaw)
        ));
        sort($allMonthKeys);

        $evolutionMensuelle = [];
        foreach ($allMonthKeys as $moisCle) {
            $parsedDate = Carbon::createFromFormat('Y-m', $moisCle);
            $evolutionMensuelle[] = [
                'periode' => $moisCle,
                'mois_nom' => $parsedDate->locale('fr')->translatedFormat('F Y'),
                'emprunts' => (int) ($monthlyEmpruntsRaw[$moisCle] ?? 0),
                'retours' => (int) ($monthlyRetoursRaw[$moisCle] ?? 0),
                'consultations' => (int) ($monthlyConsultationsRaw[$moisCle] ?? 0),
            ];
        }

        // Taux de retour sur la période
        $tauxRetour = $empruntsPeriode > 0 ? round(($retoursPeriode / $empruntsPeriode) * 100, 2) : 0.0;

        return [
            'site' => [
                'id' => $site?->id,
                'nom' => $site?->nom,
            ],
            'periode' => [
                'date_debut' => $period['date_debut'],
                'date_fin' => $period['date_fin'],
                'type' => $period['type_periode'],
            ],
            'date_generation' => now()->toIso8601String(),
            'synthese' => [
                'emprunts_realises' => $empruntsPeriode,
                'retours_effectues' => $retoursPeriode,
                'emprunts_en_cours' => $empruntsEnCours,
                'emprunts_en_retard' => $empruntsEnRetard,
                'consultations_sur_place' => $consultationsPeriode,
                'taux_retour_pourcentage' => $tauxRetour,
            ],
            'evolution_mensuelle' => $evolutionMensuelle,
        ];
    }

    /**
     * 3. Rapport du classement des livres les plus empruntés sur période.
     */
    public function getTopLivresReport(string $siteId, array $filters = []): array
    {
        $site = Site::find($siteId);
        $period = $this->resolvePeriod($filters);
        $startDate = $period['start_datetime'];
        $endDate = $period['end_datetime'];
        $limit = min(max((int) ($filters['limit'] ?? 10), 1), 100);

        $query = DB::table('operations')
            ->join('exemplaires', 'operations.exemplaire_id', '=', 'exemplaires.id')
            ->join('livres', 'exemplaires.livre_id', '=', 'livres.id')
            ->leftJoin('categories_livres', 'livres.categorie_livre_id', '=', 'categories_livres.id')
            ->where('operations.site_id', $siteId)
            ->where('operations.type', 'EMPRUNT')
            ->whereBetween('operations.date_operation', [$startDate, $endDate])
            ->whereNull('operations.deleted_at')
            ->whereNull('exemplaires.deleted_at')
            ->whereNull('livres.deleted_at');

        if (!empty($filters['categorie_livre_id'])) {
            $query->where('livres.categorie_livre_id', $filters['categorie_livre_id']);
        }

        $results = $query->select(
                'livres.id',
                'livres.code',
                'livres.titre',
                'livres.auteur',
                'livres.isbn',
                DB::raw("COALESCE(categories_livres.nom, 'Non catégorisé') as categorie"),
                DB::raw('COUNT(operations.id) as nombre_emprunts')
            )
            ->groupBy('livres.id', 'livres.code', 'livres.titre', 'livres.auteur', 'livres.isbn', 'categories_livres.nom')
            ->orderByDesc('nombre_emprunts')
            ->orderBy('livres.titre', 'asc') // Tri stable en cas d'égalité
            ->limit($limit)
            ->get();

        // Récupérer le nombre total d'exemplaires par livre pour enrichir
        $livreIds = $results->pluck('id')->toArray();
        $exemplairesCounts = Exemplaire::where('site_id', $siteId)
            ->whereIn('livre_id', $livreIds)
            ->select('livre_id', DB::raw('COUNT(*) as total'))
            ->groupBy('livre_id')
            ->pluck('total', 'livre_id')
            ->toArray();

        $ranking = [];
        $rank = 1;
        foreach ($results as $row) {
            $ranking[] = [
                'rang' => $rank++,
                'livre_id' => $row->id,
                'code' => $row->code,
                'titre' => $row->titre,
                'auteur' => $row->auteur,
                'isbn' => $row->isbn,
                'categorie' => $row->categorie,
                'nombre_emprunts' => (int) $row->nombre_emprunts,
                'total_exemplaires' => (int) ($exemplairesCounts[$row->id] ?? 0),
            ];
        }

        return [
            'site' => [
                'id' => $site?->id,
                'nom' => $site?->nom,
            ],
            'periode' => [
                'date_debut' => $period['date_debut'],
                'date_fin' => $period['date_fin'],
                'type' => $period['type_periode'],
            ],
            'limite' => $limit,
            'total_livres_classes' => count($ranking),
            'classement' => $ranking,
        ];
    }

    /**
     * 4. Rapport des retards d'emprunts avec tranches d'ancienneté.
     */
    public function getRetardsReport(string $siteId, array $filters = []): array
    {
        $site = Site::find($siteId);
        $now = now();

        $operations = Operation::with([
            'site.configuration',
            'usager',
            'exemplaire.livre.categorieLivre',
        ])
            ->where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->whereNull('date_retour_effective') // Uniquement les retards actifs
            ->whereNotNull('date_retour_prevue')
            ->when(!empty($filters['categorie_livre_id']), function ($q) use ($filters) {
                $q->whereHas('exemplaire.livre', fn($lq) => $lq->where('categorie_livre_id', $filters['categorie_livre_id']));
            })
            ->when(!empty($filters['usager_id']), fn($q) => $q->where('usager_id', $filters['usager_id']))
            ->orderBy('date_retour_prevue', 'asc')
            ->get();

        $items = [];
        $trancheMoins7Jours = 0;
        $tranche7A30Jours = 0;
        $tranchePlus30Jours = 0;

        foreach ($operations as $op) {
            $graceDays = (int) ($op->site?->configuration?->nombre_jours_grace ?? 0);
            $dueDate = Carbon::parse($op->date_retour_prevue);
            $limitDate = $dueDate->copy()->addDays($graceDays);

            if ($now->isAfter($limitDate)) {
                $joursRetard = max(1, (int) $dueDate->diffInDays($now, false));

                // Classification par tranches strictes sans chevauchement
                if ($joursRetard < 7) {
                    $tranche = 'moins_de_7_jours';
                    $trancheMoins7Jours++;
                } elseif ($joursRetard <= 30) {
                    $tranche = 'de_7_a_30_jours';
                    $tranche7A30Jours++;
                } else {
                    $tranche = 'plus_de_30_jours';
                    $tranchePlus30Jours++;
                }

                $items[] = [
                    'operation_id' => $op->id,
                    'statut' => $op->statut,
                    'date_emprunt' => $op->date_operation ? Carbon::parse($op->date_operation)->toDateString() : null,
                    'date_retour_prevue' => $dueDate->toDateString(),
                    'jours_retard' => $joursRetard,
                    'tranche_retard' => $tranche,
                    'livre' => [
                        'id' => $op->exemplaire?->livre?->id,
                        'code' => $op->exemplaire?->livre?->code,
                        'titre' => $op->exemplaire?->livre?->titre ?? 'Livre inconnu',
                        'auteur' => $op->exemplaire?->livre?->auteur,
                        'categorie' => $op->exemplaire?->livre?->categorieLivre?->nom ?? 'Non catégorisé',
                    ],
                    'exemplaire' => [
                        'id' => $op->exemplaire?->id,
                        'code' => $op->exemplaire?->code,
                        'etat' => $op->exemplaire?->etat,
                        'localisation' => $op->exemplaire?->localisation,
                    ],
                    'usager' => [
                        'id' => $op->usager?->id,
                        'numero' => $op->usager?->numero,
                        'nom' => $op->usager?->nom,
                        'prenom' => $op->usager?->prenom,
                        'email' => $op->usager?->email,
                        'contact' => $op->usager?->contact,
                    ],
                ];
            }
        }

        // Tri par nombre de jours de retard décroissant
        usort($items, fn($a, $b) => $b['jours_retard'] <=> $a['jours_retard']);

        return [
            'site' => [
                'id' => $site?->id,
                'nom' => $site?->nom,
            ],
            'date_reference' => $now->toDateString(),
            'synthese_tranches' => [
                'total_retards_actifs' => count($items),
                'moins_de_7_jours' => $trancheMoins7Jours,
                'de_7_a_30_jours' => $tranche7A30Jours,
                'plus_de_30_jours' => $tranchePlus30Jours,
            ],
            'retards' => $items,
        ];
    }

    /**
     * 5. Rapport des acquisitions (Achats, Dons, Quantités et Montants).
     */
    public function getAcquisitionsReport(string $siteId, array $filters = []): array
    {
        $site = Site::find($siteId);
        $period = $this->resolvePeriod($filters);
        $startDate = $period['start_datetime'];
        $endDate = $period['end_datetime'];

        $baseQuery = Acquisition::where('site_id', $siteId)
            ->whereBetween('date_acquisition', [$startDate, $endDate]);

        if (!empty($filters['type'])) {
            $baseQuery->where('type', $filters['type']);
        }

        $totalOperations = (clone $baseQuery)->count();
        $quantiteTotale = (int) (clone $baseQuery)->sum('quantite');

        // Achats
        $achatsQuery = (clone $baseQuery)->where('type', 'ACHAT');
        $operationsAchats = (clone $achatsQuery)->count();
        $quantiteAchats = (int) (clone $achatsQuery)->sum('quantite');
        $montantAchats = (float) (clone $achatsQuery)->sum('cout');

        // Dons
        $donsQuery = (clone $baseQuery)->where('type', 'DON');
        $operationsDons = (clone $donsQuery)->count();
        $quantiteDons = (int) (clone $donsQuery)->sum('quantite');

        // Autres acquisitions
        $autresQuery = (clone $baseQuery)->whereNotIn('type', ['ACHAT', 'DON']);
        $operationsAutres = (clone $autresQuery)->count();
        $quantiteAutres = (int) (clone $autresQuery)->sum('quantite');
        $montantAutres = (float) (clone $autresQuery)->sum('cout');

        // Évolution mensuelle
        $acquisitionsMensuelles = DB::table('acquisitions')
            ->where('site_id', $siteId)
            ->whereBetween('date_acquisition', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->select(
                DB::raw("DATE_FORMAT(date_acquisition, '%Y-%m') as mois_cle"),
                DB::raw("COUNT(*) as total_operations"),
                DB::raw("SUM(quantite) as quantite_totale"),
                DB::raw("SUM(CASE WHEN type = 'ACHAT' THEN quantite ELSE 0 END) as quantite_achats"),
                DB::raw("SUM(CASE WHEN type = 'ACHAT' THEN cout ELSE 0 END) as montant_achats"),
                DB::raw("SUM(CASE WHEN type = 'DON' THEN quantite ELSE 0 END) as quantite_dons")
            )
            ->groupBy('mois_cle')
            ->orderBy('mois_cle')
            ->get()
            ->map(function ($row) {
                $parsedDate = Carbon::createFromFormat('Y-m', $row->mois_cle);
                return [
                    'periode' => $row->mois_cle,
                    'mois_nom' => $parsedDate->locale('fr')->translatedFormat('F Y'),
                    'total_operations' => (int) $row->total_operations,
                    'quantite_totale' => (int) $row->quantite_totale,
                    'quantite_achats' => (int) $row->quantite_achats,
                    'montant_achats' => (float) $row->montant_achats,
                    'quantite_dons' => (int) $row->quantite_dons,
                ];
            });

        // Taux de dons vs achats
        $pourcentageQuantiteAchats = $quantiteTotale > 0 ? round(($quantiteAchats / $quantiteTotale) * 100, 2) : 0.0;
        $pourcentageQuantiteDons = $quantiteTotale > 0 ? round(($quantiteDons / $quantiteTotale) * 100, 2) : 0.0;

        return [
            'site' => [
                'id' => $site?->id,
                'nom' => $site?->nom,
            ],
            'periode' => [
                'date_debut' => $period['date_debut'],
                'date_fin' => $period['date_fin'],
                'type' => $period['type_periode'],
            ],
            'synthese' => [
                'total_operations' => $totalOperations,
                'quantite_totale_acquise' => $quantiteTotale,
                'achats' => [
                    'nombre_operations' => $operationsAchats,
                    'quantite_acquise' => $quantiteAchats,
                    'montant_total' => $montantAchats,
                    'part_quantite_pourcentage' => $pourcentageQuantiteAchats,
                ],
                'dons' => [
                    'nombre_operations' => $operationsDons,
                    'quantite_acquise' => $quantiteDons,
                    'part_quantite_pourcentage' => $pourcentageQuantiteDons,
                ],
                'autres' => [
                    'nombre_operations' => $operationsAutres,
                    'quantite_acquise' => $quantiteAutres,
                    'montant_total' => $montantAutres,
                ],
            ],
            'evolution_mensuelle' => $acquisitionsMensuelles,
        ];
    }

    /**
     * 6. Rapport de fréquentation (Délégation au PresenceService avec distinction usagers uniques).
     */
    public function getFrequentationReport(string $siteId, array $filters = []): array
    {
        $stats = $this->presenceService->getStatistiques($siteId, $filters);

        // Enrichir avec le nombre de visiteurs uniques réels
        $period = $this->resolvePeriod($filters);
        $visiteursUniques = DB::table('presences')
            ->where('site_id', $siteId)
            ->whereBetween('date_presence', [$period['date_debut'], $period['date_fin']])
            ->whereNotNull('usager_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->count('usager_id');

        $stats['bibliotheque']['visiteurs_uniques_periode'] = $visiteursUniques;
        $stats['periode'] = [
            'date_debut' => $period['date_debut'],
            'date_fin' => $period['date_fin'],
            'type' => $period['type_periode'],
        ];

        return $stats;
    }

    /**
     * 7. Rapport d'inventaire historique (Snapshots passés et journal des mouvements).
     */
    public function getInventaireHistoriqueReport(string $siteId, array $filters = []): array
    {
        $site = Site::find($siteId);
        $period = $this->resolvePeriod($filters);
        $startDate = $period['date_debut'];
        $endDate = $period['date_fin'];

        // 1. Récupération des snapshots existants
        $snapshotsQuery = InventaireSnapshot::where('site_id', $siteId)
            ->whereBetween('date_snapshot', [$startDate, $endDate])
            ->orderBy('date_snapshot', 'desc');

        $snapshots = $snapshotsQuery->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'date_snapshot' => $s->date_snapshot->toDateString(),
                'annee' => $s->annee,
                'mois' => $s->mois,
                'total_livres' => $s->total_livres,
                'total_exemplaires' => $s->total_exemplaires,
                'exemplaires_disponibles' => $s->total_disponibles,
                'exemplaires_empruntes' => $s->total_empruntes,
                'exemplaires_en_consultation' => $s->total_consultation,
                'exemplaires_reserves' => $s->total_reserves,
                'exemplaires_indisponibles' => $s->total_indisponibles,
                'exemplaires_perdus' => $s->total_perdus,
                'repartition_par_etat' => $s->repartition_etats,
                'repartition_par_statut' => $s->repartition_statuts,
                'repartition_par_categorie' => $s->repartition_categories,
                'metadata' => $s->metadata,
                'created_at' => $s->created_at->toIso8601String(),
            ];
        });

        // 2. Derniers mouvements d'exemplaires enregistrés
        $perPageMouvements = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $mouvementsQuery = ExemplaireMouvement::with(['exemplaire.livre', 'utilisateur'])
            ->where('site_id', $siteId)
            ->whereBetween('created_at', [$period['start_datetime'], $period['end_datetime']]);

        if (!empty($filters['exemplaire_id'])) {
            $mouvementsQuery->where('exemplaire_id', $filters['exemplaire_id']);
        }

        $mouvementsPaginator = $mouvementsQuery->orderBy('created_at', 'desc')
            ->paginate($perPageMouvements);

        $mouvementsItems = collect($mouvementsPaginator->items())->map(function ($m) {
            return [
                'id' => $m->id,
                'date_mouvement' => $m->created_at ? $m->created_at->toIso8601String() : null,
                'motif' => $m->motif,
                'ancien_etat' => $m->ancien_etat,
                'nouvel_etat' => $m->nouveau_etat,
                'ancien_statut' => $m->ancien_statut,
                'nouveau_statut' => $m->nouveau_statut,
                'exemplaire' => [
                    'id' => $m->exemplaire?->id,
                    'code' => $m->exemplaire?->code,
                    'livre_titre' => $m->exemplaire?->livre?->titre ?? 'Livre inconnu',
                ],
                'utilisateur' => $m->utilisateur ? [
                    'id' => $m->utilisateur->id,
                    'nom' => $m->utilisateur->nom ?? 'Utilisateur',
                    'prenom' => $m->utilisateur->prenom ?? '',
                ] : null,
            ];
        });

        return [
            'site' => [
                'id' => $site?->id,
                'nom' => $site?->nom,
            ],
            'periode' => [
                'date_debut' => $startDate,
                'date_fin' => $endDate,
                'type' => $period['type_periode'],
            ],
            'snapshots_disponibles' => count($snapshots),
            'snapshots' => $snapshots,
            'journal_mouvements' => [
                'data' => $mouvementsItems,
                'pagination' => [
                    'current_page' => $mouvementsPaginator->currentPage(),
                    'last_page' => $mouvementsPaginator->lastPage(),
                    'per_page' => $mouvementsPaginator->perPage(),
                    'total' => $mouvementsPaginator->total(),
                ],
            ],
            'avertissement' => count($snapshots) === 0
                ? "Aucun snapshot d'inventaire n'est disponible pour la période sélectionnée. Les snapshots s'accumulent périodiquement ou peuvent être déclenchés à la demande."
                : null,
        ];
    }

    /**
     * Déclencher ou mettre à jour un snapshot d'inventaire pour un site et une date donnée.
     */
    public function createInventaireSnapshot(string $siteId, ?string $date = null, ?string $userId = null): InventaireSnapshot
    {
        $snapshotDate = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $exemplairesBase = Exemplaire::where('site_id', $siteId);

        $totalExemplaires = (clone $exemplairesBase)->count();
        $disponibles = (clone $exemplairesBase)->where('statut', 'DISPONIBLE')->where('etat', '!=', 'PERDU')->count();
        $empruntes = (clone $exemplairesBase)->where('statut', 'EMPRUNTE')->count();
        $enConsultation = (clone $exemplairesBase)->where('statut', 'EN_CONSULTATION')->count();
        $reserves = (clone $exemplairesBase)->where('statut', 'RESERVE')->count();
        $indisponibles = (clone $exemplairesBase)->where('statut', 'INDISPONIBLE')->where('etat', '!=', 'PERDU')->count();
        $perdus = (clone $exemplairesBase)->where('etat', 'PERDU')->count();

        $totalLivres = Livre::whereHas('exemplaires', fn($q) => $q->where('site_id', $siteId))->count();

        $etatsRaw = Exemplaire::where('site_id', $siteId)
            ->select('etat', DB::raw('COUNT(*) as total'))
            ->groupBy('etat')
            ->pluck('total', 'etat')
            ->toArray();

        $statutsRaw = Exemplaire::where('site_id', $siteId)
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $snapshotDateCarbon = Carbon::parse($snapshotDate);

        return InventaireSnapshot::updateOrCreate(
            [
                'site_id' => $siteId,
                'date_snapshot' => $snapshotDate,
            ],
            [
                'annee' => $snapshotDateCarbon->year,
                'mois' => $snapshotDateCarbon->month,
                'total_livres' => $totalLivres,
                'total_exemplaires' => $totalExemplaires,
                'total_disponibles' => $disponibles,
                'total_empruntes' => $empruntes,
                'total_consultation' => $enConsultation,
                'total_reserves' => $reserves,
                'total_indisponibles' => $indisponibles,
                'total_perdus' => $perdus,
                'repartition_etats' => $etatsRaw,
                'repartition_statuts' => $statutsRaw,
                'metadata' => [
                    'declenche_par' => $userId ?? 'system',
                    'horodatage' => now()->toIso8601String(),
                ],
            ]
        );
    }
}
