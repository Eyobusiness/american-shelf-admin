<?php

namespace App\Services\SuperAdmin;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GlobalStatisticsService
{
    /**
     * Calcul consolidé de l'ensemble des statistiques de la plateforme pour le Super Admin.
     */
    public function getGlobalDashboardData(?string $dateDebut = null, ?string $dateFin = null, int $topLimit = 5): array
    {
        $startDate = $dateDebut
            ? Carbon::parse($dateDebut)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = $dateFin
            ? Carbon::parse($dateFin)->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $today = now()->toDateString();

        // =========================================================================
        // 1. STATISTIQUES DES SITES
        // =========================================================================
        $sitesBase = DB::table('sites')->whereNull('deleted_at');
        $totalSites = (clone $sitesBase)->count();
        $sitesActifs = (clone $sitesBase)->where('statut', 'ACTIF')->count();
        $sitesInactifs = (clone $sitesBase)->where('statut', 'INACTIF')->count();
        $sitesSuspendus = (clone $sitesBase)->where('statut', 'SUSPENDU')->count();

        // =========================================================================
        // 2. FONDS DOCUMENTAIRE GLOBAL
        // =========================================================================
        $totalLivres = DB::table('livres')->whereNull('deleted_at')->count();
        $totalExemplaires = DB::table('exemplaires')->whereNull('deleted_at')->count();
        $totalCategories = DB::table('categories_livres')->whereNull('deleted_at')->count();
        $categoriesUniques = DB::table('categories_livres')->whereNull('deleted_at')->distinct()->count('code');

        // Répartition par état physique
        $exemplairesParEtat = DB::table('exemplaires')
            ->whereNull('deleted_at')
            ->select('etat', DB::raw('count(*) as total'))
            ->groupBy('etat')
            ->pluck('total', 'etat')
            ->toArray();

        // Répartition par statut opérationnel
        $exemplairesParStatut = DB::table('exemplaires')
            ->whereNull('deleted_at')
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        // =========================================================================
        // 3. STATISTIQUES DES EMPRUNTS GLOBAUX
        // =========================================================================
        $empruntsBase = DB::table('operations')->where('type', 'EMPRUNT');
        $empruntsTotalHistorique = (clone $empruntsBase)->count();
        $empruntsPeriode = (clone $empruntsBase)
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->count();
        $empruntsEnCours = (clone $empruntsBase)
            ->where('statut', 'EN_COURS')
            ->count();

        $empruntsTermines = (clone $empruntsBase)
            ->where(function ($q) {
                $q->where('statut', 'RETOURNE')
                  ->orWhere(function ($sub) {
                      $sub->where('statut', 'EN_RETARD')
                          ->whereNotNull('date_retour_effective');
                  });
            })
            ->whereBetween('date_retour_effective', [$startDate, $endDate])
            ->count();

        $empruntsEnRetard = (clone $empruntsBase)
            ->where(function ($q) {
                $q->where(function ($sub1) {
                    $sub1->where('statut', 'EN_RETARD')
                         ->whereNull('date_retour_effective');
                })
                ->orWhere(function ($sub2) {
                    $sub2->where('statut', 'EN_COURS')
                         ->whereNotNull('date_retour_prevue')
                         ->where('date_retour_prevue', '<', now());
                });
            })
            ->count();

        // =========================================================================
        // 4. STATISTIQUES DES CONSULTATIONS SUR PLACE
        // =========================================================================
        $consultationsBase = DB::table('operations')->where('type', 'CONSULTATION');
        $consultationsTotalHistorique = (clone $consultationsBase)->count();
        $consultationsPeriode = (clone $consultationsBase)
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->count();
        $consultationsEnCours = (clone $consultationsBase)
            ->where('statut', 'EN_COURS')
            ->count();

        $consultationsTerminees = (clone $consultationsBase)
            ->where(function ($q) {
                $q->where('statut', 'RETOURNE')
                  ->orWhere(function ($sub) {
                      $sub->where('statut', 'EN_RETARD')
                          ->whereNotNull('date_retour_effective');
                  });
            })
            ->whereBetween('date_retour_effective', [$startDate, $endDate])
            ->count();

        // =========================================================================
        // 5. STATISTIQUES DE FRÉQUENTATION / PRÉSENCES
        // =========================================================================
        $presencesBase = DB::table('presences');
        $presencesPeriode = (clone $presencesBase)
            ->whereBetween('date_presence', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();
        $presencesDuJour = (clone $presencesBase)
            ->whereDate('date_presence', $today)
            ->count();
        $presencesDuMois = (clone $presencesBase)
            ->whereBetween('date_presence', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        $presencesUsagers = (clone $presencesBase)
            ->whereBetween('date_presence', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('usager_id')
            ->count();

        $presencesAnonymes = (clone $presencesBase)
            ->whereBetween('date_presence', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNull('usager_id')
            ->count();

        // =========================================================================
        // 6. COMPARAISON ENTRE LES DIFFÉRENTS SITES
        // =========================================================================
        $comparaisonSites = $this->buildSitesComparison($startDate, $endDate);

        // =========================================================================
        // 7. ÉVOLUTION TEMPORELLE DES ACTIVITÉS (6 derniers mois)
        // =========================================================================
        $evolutionActivites = $this->buildActivityEvolution(6);

        return [
            'periode' => [
                'date_debut' => $startDate->toDateString(),
                'date_fin' => $endDate->toDateString(),
            ],
            'sites' => [
                'total' => $totalSites,
                'actifs' => $sitesActifs,
                'inactifs' => $sitesInactifs,
                'suspendus' => $sitesSuspendus,
            ],
            'fonds_documentaire' => [
                'total_livres' => $totalLivres,
                'total_exemplaires' => $totalExemplaires,
                'total_categories' => $totalCategories,
                'categories_uniques' => $categoriesUniques,
                'exemplaires_par_etat' => $exemplairesParEtat,
                'exemplaires_par_statut' => $exemplairesParStatut,
            ],
            'emprunts' => [
                'total_periode' => $empruntsPeriode,
                'en_cours' => $empruntsEnCours,
                'termines' => $empruntsTermines,
                'en_retard' => $empruntsEnRetard,
                'total_historique' => $empruntsTotalHistorique,
            ],
            'consultations' => [
                'total_periode' => $consultationsPeriode,
                'en_cours' => $consultationsEnCours,
                'terminees' => $consultationsTerminees,
                'total_historique' => $consultationsTotalHistorique,
            ],
            'presences' => [
                'total_periode' => $presencesPeriode,
                'du_jour' => $presencesDuJour,
                'du_mois' => $presencesDuMois,
                'usagers_adherents' => $presencesUsagers,
                'visiteurs_anonymes' => $presencesAnonymes,
            ],
            'comparaison_sites' => $comparaisonSites,
            'evolution_activites' => $evolutionActivites,
        ];
    }

    /**
     * Construire la matrice comparative entre les différents sites.
     */
    protected function buildSitesComparison(Carbon $startDate, Carbon $endDate): array
    {
        $sites = DB::table('sites')->whereNull('deleted_at')->orderBy('nom')->get();

        // Dictionnaires d'agrégation par site
        $livresParSite = DB::table('livres')
            ->whereNull('deleted_at')
            ->select('site_id', DB::raw('count(*) as total'))
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $exemplairesParSite = DB::table('exemplaires')
            ->whereNull('deleted_at')
            ->select('site_id', DB::raw('count(*) as total'))
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $empruntsActifsParSite = DB::table('operations')
            ->where('type', 'EMPRUNT')
            ->where('statut', 'EN_COURS')
            ->select('site_id', DB::raw('count(*) as total'))
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $consultationsActivesParSite = DB::table('operations')
            ->where('type', 'CONSULTATION')
            ->where('statut', 'EN_COURS')
            ->select('site_id', DB::raw('count(*) as total'))
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $presencesPeriodeParSite = DB::table('presences')
            ->whereBetween('date_presence', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('site_id', DB::raw('count(*) as total'))
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $usagersParSite = DB::table('usager_sites')
            ->whereNull('deleted_at')
            ->select('site_id', DB::raw('count(*) as total'))
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $comparison = [];
        foreach ($sites as $site) {
            $comparison[] = [
                'site_id' => $site->id,
                'code' => $site->code,
                'nom' => $site->nom,
                'statut' => $site->statut,
                'total_livres' => (int) ($livresParSite[$site->id] ?? 0),
                'total_exemplaires' => (int) ($exemplairesParSite[$site->id] ?? 0),
                'emprunts_en_cours' => (int) ($empruntsActifsParSite[$site->id] ?? 0),
                'consultations_en_cours' => (int) ($consultationsActivesParSite[$site->id] ?? 0),
                'presences_periode' => (int) ($presencesPeriodeParSite[$site->id] ?? 0),
                'total_usagers' => (int) ($usagersParSite[$site->id] ?? 0),
            ];
        }

        return $comparison;
    }

    /**
     * Évolution temporelle mensuelle des activités consolidées.
     */
    protected function buildActivityEvolution(int $monthsCount = 6): array
    {
        $evolution = [];

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $start = (clone $monthDate)->startOfMonth()->startOfDay();
            $end = (clone $monthDate)->endOfMonth()->endOfDay();

            $emprunts = DB::table('operations')
                ->where('type', 'EMPRUNT')
                ->whereBetween('date_operation', [$start, $end])
                ->count();

            $consultations = DB::table('operations')
                ->where('type', 'CONSULTATION')
                ->whereBetween('date_operation', [$start, $end])
                ->count();

            $presences = DB::table('presences')
                ->whereBetween('date_presence', [$start->toDateString(), $end->toDateString()])
                ->count();

            $evolution[] = [
                'mois' => $monthKey,
                'label' => $monthDate->translatedFormat('F Y'),
                'emprunts' => $emprunts,
                'consultations' => $consultations,
                'presences' => $presences,
            ];
        }

        return $evolution;
    }
}
