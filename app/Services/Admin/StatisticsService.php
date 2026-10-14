<?php

namespace App\Services\Admin;

use App\Models\Acquisition;
use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Operation;
use App\Models\Presence;
use App\Models\Reservation;
use App\Models\UsagerSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * Calcul dynamique complet des statistiques du tableau de bord pour un tenant donne.
     */
    public function getDashboardData(string $siteId, ?string $dateDebut = null, ?string $dateFin = null, int $topLimit = 5): array
    {
        // Periode par defaut : debut et fin du mois en cours si non specifiee
        $startDate = $dateDebut
            ? Carbon::parse($dateDebut)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = $dateFin
            ? Carbon::parse($dateFin)->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $today = now()->toDateString();

        // 1. Documents (Livres & Exemplaires)
        $nombreLivres = Livre::where('site_id', $siteId)->count();
        $nombreExemplaires = Exemplaire::where('site_id', $siteId)->count();
        $exemplairesDisponibles = Exemplaire::where('site_id', $siteId)->where('statut', 'DISPONIBLE')->count();
        $exemplairesEmpruntes = Exemplaire::where('site_id', $siteId)->where('statut', 'EMPRUNTE')->count();
        $exemplairesEnConsultation = Exemplaire::where('site_id', $siteId)->where('statut', 'EN_CONSULTATION')->count();
        $exemplairesIndisponibles = Exemplaire::where('site_id', $siteId)->where('statut', 'INDISPONIBLE')->count();
        // Correction audit : le décompte des exemplaires perdus doit utiliser l'état physique 'PERDU' et non le statut opérationnel
        $exemplairesPerdus = Exemplaire::where('site_id', $siteId)->where('etat', 'PERDU')->count();

        // 2. Usagers (via UsagerSite pour garantir l'isolation tenant)
        $totalUsagers = UsagerSite::where('site_id', $siteId)->count();
        $adhesionsActives = UsagerSite::where('site_id', $siteId)->where('statut', 'ACTIF')->count();
        $adhesionsSuspendues = UsagerSite::where('site_id', $siteId)->where('statut', 'SUSPENDU')->count();
        $adhesionsExpirees = UsagerSite::where('site_id', $siteId)->where('statut', 'EXPIRE')->count();
        $nouveauxUsagersPeriode = UsagerSite::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Usagers par categorie
        $categoriesUsagers = CategorieUsager::where('site_id', $siteId)->get()->map(function ($cu) use ($siteId) {
            $cnt = UsagerSite::where('site_id', $siteId)->where('categorie_usager_id', $cu->id)->count();
            return [
                'categorie_id' => $cu->id,
                'code' => $cu->code,
                'nom' => $cu->nom,
                'libelle' => $cu->nom,
                'total_usagers' => $cnt,
                'nombre_usagers' => $cnt,
            ];
        });

        // 3. Activites - Emprunts (via Operation)
        $empruntsDuJour = Operation::where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->whereDate('date_operation', $today)
            ->count();

        $empruntsPeriode = Operation::where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->count();

        $empruntsEnCours = Operation::where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->where('statut', 'EN_COURS')
            ->count();

        // Correction audit : Les retours effectifs de la période doivent être calculés sur date_retour_effective
        // et inclure les statuts de retour réels (RETOURNE ou clôturé avec retard mais retourné)
        $empruntsRetournes = Operation::where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
            ->where(function ($q) {
                $q->where('statut', 'RETOURNE')
                  ->orWhere(function ($sub) {
                      $sub->where('statut', 'EN_RETARD')
                          ->whereNotNull('date_retour_effective');
                  });
            })
            ->whereBetween('date_retour_effective', [$startDate, $endDate])
            ->count();

        // Emprunts actuellement en retard (actifs et non clôturés)
        $empruntsEnRetard = Operation::where('site_id', $siteId)
            ->where('type', 'EMPRUNT')
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

        // 4. Activites - Consultations (via Operation)
        $consultationsDuJour = Operation::where('site_id', $siteId)
            ->where('type', 'CONSULTATION')
            ->whereDate('date_operation', $today)
            ->count();

        $consultationsPeriode = Operation::where('site_id', $siteId)
            ->where('type', 'CONSULTATION')
            ->whereBetween('date_operation', [$startDate, $endDate])
            ->count();

        $consultationsEnCours = Operation::where('site_id', $siteId)
            ->where('type', 'CONSULTATION')
            ->where('statut', 'EN_COURS')
            ->count();

        $consultationsTerminees = Operation::where('site_id', $siteId)
            ->where('type', 'CONSULTATION')
            ->where(function ($q) {
                $q->where('statut', 'RETOURNE')
                  ->orWhere(function ($sub) {
                      $sub->where('statut', 'EN_RETARD')
                          ->whereNotNull('date_retour_effective');
                  });
            })
            ->whereBetween('date_retour_effective', [$startDate, $endDate])
            ->count();

        // 5. Activites - Reservations (via Reservation)
        $reservationsActives = Reservation::where('site_id', $siteId)
            ->whereIn('statut', ['EN_ATTENTE', 'DISPONIBLE'])
            ->count();

        $reservationsEnAttente = Reservation::where('site_id', $siteId)
            ->where('statut', 'EN_ATTENTE')
            ->count();

        $reservationsExpirees = Reservation::where('site_id', $siteId)
            ->where('statut', 'EXPIREE')
            ->count();

        $reservationsAnnulees = Reservation::where('site_id', $siteId)
            ->where('statut', 'ANNULEE')
            ->count();

        $reservationsHonorees = Reservation::where('site_id', $siteId)
            ->where('statut', 'HONOREE')
            ->count();

        // 6. Presences (via Presence)
        $presencesPeriode = Presence::where('site_id', $siteId)
            ->whereBetween('date_presence', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        $presencesDuJour = Presence::where('site_id', $siteId)
            ->whereDate('date_presence', $today)
            ->count();

        $presencesDuMois = Presence::where('site_id', $siteId)
            ->whereBetween('date_presence', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        $presencesDeLAnnee = Presence::where('site_id', $siteId)
            ->whereBetween('date_presence', [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()])
            ->count();

        // 7. Acquisitions
        $acquisitionsPeriode = Acquisition::where('site_id', $siteId)
            ->whereBetween('date_acquisition', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        $quantiteAcquise = (int) Acquisition::where('site_id', $siteId)
            ->whereBetween('date_acquisition', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('quantite');

        $acquisitionsAchats = Acquisition::where('site_id', $siteId)
            ->where('type', 'ACHAT')
            ->whereBetween('date_acquisition', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        $acquisitionsDons = Acquisition::where('site_id', $siteId)
            ->where('type', 'DON')
            ->whereBetween('date_acquisition', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        $montantAchats = (float) Acquisition::where('site_id', $siteId)
            ->where('type', 'ACHAT')
            ->whereBetween('date_acquisition', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('cout');

        // 8. Top des livres les plus empruntes (filtré par période sélectionnée et configurable)
        $topLivres = Operation::query()
            ->join('exemplaires', 'operations.exemplaire_id', '=', 'exemplaires.id')
            ->join('livres', 'exemplaires.livre_id', '=', 'livres.id')
            ->where('operations.site_id', $siteId)
            ->where('operations.type', 'EMPRUNT')
            ->whereBetween('operations.date_operation', [$startDate, $endDate])
            ->select('livres.id as livre_id', 'livres.titre', 'livres.code', DB::raw('count(operations.id) as nombre_emprunts'))
            ->groupBy('livres.id', 'livres.titre', 'livres.code')
            ->orderByDesc('nombre_emprunts')
            ->limit($topLimit ?? 5)
            ->get()
            ->map(fn($item) => [
                'livre_id' => $item->livre_id,
                'titre' => $item->titre,
                'code' => $item->code,
                'nombre_emprunts' => (int) $item->nombre_emprunts,
            ]);

        // 9. Statistiques par categorie de livres
        $categoriesLivres = CategorieLivre::where('site_id', $siteId)
            ->withCount('livres')
            ->get();

        $livresParCategorie = $categoriesLivres->map(fn($cat) => [
            'categorie_id' => $cat->id,
            'code' => $cat->code,
            'nom' => $cat->nom,
            'total_livres' => (int) $cat->livres_count,
        ]);

        $empruntsParCategorie = $categoriesLivres->map(function ($cat) use ($siteId, $startDate, $endDate) {
            $total = Operation::query()
                ->join('exemplaires', 'operations.exemplaire_id', '=', 'exemplaires.id')
                ->join('livres', 'exemplaires.livre_id', '=', 'livres.id')
                ->where('operations.site_id', $siteId)
                ->where('operations.type', 'EMPRUNT')
                ->where('livres.categorie_livre_id', $cat->id)
                ->whereBetween('operations.date_operation', [$startDate, $endDate])
                ->count();

            return [
                'categorie_id' => $cat->id,
                'code' => $cat->code,
                'nom' => $cat->nom,
                'total_emprunts' => (int) $total,
            ];
        });

        // 10. Evolution mensuelle sur 12 mois (annee de startDate)
        $year = $startDate->year;
        $moisNoms = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
        $evolutionMensuelle = [];
        for ($m = 1; $m <= 12; $m++) {
            $mStart = Carbon::createFromDate($year, $m, 1)->startOfMonth();
            $mEnd = Carbon::createFromDate($year, $m, 1)->endOfMonth();

            $empCount = Operation::where('site_id', $siteId)
                ->where('type', 'EMPRUNT')
                ->whereBetween('date_operation', [$mStart, $mEnd])
                ->count();

            $retCount = Operation::where('site_id', $siteId)
                ->where('type', 'EMPRUNT')
                ->where(function ($q) {
                    $q->where('statut', 'RETOURNE')
                      ->orWhere(function ($sub) {
                          $sub->where('statut', 'EN_RETARD')
                              ->whereNotNull('date_retour_effective');
                      });
                })
                ->whereBetween('date_retour_effective', [$mStart, $mEnd])
                ->count();

            $consCount = Operation::where('site_id', $siteId)
                ->where('type', 'CONSULTATION')
                ->whereBetween('date_operation', [$mStart, $mEnd])
                ->count();

            $presCount = Presence::where('site_id', $siteId)
                ->whereBetween('date_presence', [$mStart->toDateString(), $mEnd->toDateString()])
                ->count();

            $evolutionMensuelle[] = [
                'mois' => $m,
                'mois_nom' => $moisNoms[$m - 1],
                'label' => $moisNoms[$m - 1],
                'emprunts' => $empCount,
                'retours' => $retCount,
                'consultations' => $consCount,
                'presences' => $presCount,
                'passages' => $presCount,
            ];
        }

        // 11. Dernieres activites reelles (operations de la bibliotheque)
        $dernieresActivites = Operation::where('site_id', $siteId)
            ->with(['usager:id,nom,prenom,numero', 'exemplaire.livre:id,titre,code'])
            ->orderByDesc('date_operation')
            ->limit(6)
            ->get()
            ->map(function ($op) {
                $typeLabels = [
                    'EMPRUNT' => 'Emprunt',
                    'RETOUR' => 'Retour',
                    'CONSULTATION' => 'Consultation',
                    'RESERVATION' => 'Réservation',
                ];
                $dateFormatted = $op->date_operation ? \Carbon\Carbon::parse($op->date_operation)->format('d/m/Y H:i') : '';
                return [
                    'id' => $op->id,
                    'type' => $op->type, // EMPRUNT, CONSULTATION
                    'type_label' => $typeLabels[$op->type] ?? $op->type,
                    'statut' => $op->statut,
                    'date' => $dateFormatted,
                    'date_operation' => $op->date_operation?->toIso8601String(),
                    'date_retour_prevue' => $op->date_retour_prevue?->toIso8601String(),
                    'usager_nom' => $op->usager ? trim($op->usager->prenom . ' ' . $op->usager->nom) : 'Usager',
                    'usager_numero' => $op->usager?->numero ?? '',
                    'livre_titre' => $op->exemplaire?->livre?->titre ?? 'Livre',
                    'livre_code' => $op->exemplaire?->livre?->code ?? '',
                    'exemplaire_code' => $op->exemplaire?->code_barre ?? '',
                ];
            });

        return [
            'periode' => [
                'date_debut' => $startDate->toDateString(),
                'date_fin' => $endDate->toDateString(),
            ],
            'documents' => [
                'total_livres' => $nombreLivres,
                'nombre_livres' => $nombreLivres,
                'total_exemplaires' => $nombreExemplaires,
                'nombre_exemplaires' => $nombreExemplaires,
                'exemplaires_disponibles' => $exemplairesDisponibles,
                'nombre_exemplaires_disponibles' => $exemplairesDisponibles,
                'exemplaires_empruntes' => $exemplairesEmpruntes,
                'nombre_exemplaires_empruntes' => $exemplairesEmpruntes,
                'exemplaires_consultation' => $exemplairesEnConsultation,
                'nombre_exemplaires_en_consultation' => $exemplairesEnConsultation,
                'exemplaires_indisponibles' => $exemplairesIndisponibles,
                'nombre_exemplaires_indisponibles' => $exemplairesIndisponibles,
                'exemplaires_perdus' => $exemplairesPerdus,
                'nombre_exemplaires_perdus' => $exemplairesPerdus,
            ],
            'usagers' => [
                'total_usagers' => $totalUsagers,
                'nombre_total_usagers' => $totalUsagers,
                'adherents_actifs' => $adhesionsActives,
                'nombre_adhesions_actives' => $adhesionsActives,
                'adhesions_suspendues' => $adhesionsSuspendues,
                'nombre_adhesions_suspendues' => $adhesionsSuspendues,
                'adhesions_expirees' => $adhesionsExpirees,
                'nombre_adhesions_expirees' => $adhesionsExpirees,
                'nouvelles_inscriptions' => $nouveauxUsagersPeriode,
                'nouveaux_usagers_periode' => $nouveauxUsagersPeriode,
                'repartition_categories' => $categoriesUsagers,
            ],
            'activites' => [
                'emprunts' => [
                    'du_jour' => $empruntsDuJour,
                    'periode' => $empruntsPeriode,
                    'sur_periode' => $empruntsPeriode,
                    'en_cours' => $empruntsEnCours,
                    'retournes' => $empruntsRetournes,
                    'retours' => $empruntsRetournes,
                    'en_retard' => $empruntsEnRetard,
                ],
                'consultations' => [
                    'du_jour' => $consultationsDuJour,
                    'periode' => $consultationsPeriode,
                    'sur_periode' => $consultationsPeriode,
                    'en_cours' => $consultationsEnCours,
                    'terminees' => $consultationsTerminees,
                ],
                'reservations' => [
                    'actives' => $reservationsActives,
                    'confirmees' => $reservationsActives,
                    'en_attente' => $reservationsEnAttente,
                    'expirees' => $reservationsExpirees,
                    'annulees' => $reservationsAnnulees,
                    'honorees' => $reservationsHonorees,
                ],
            ],
            'presences' => [
                'du_jour' => $presencesDuJour,
                'du_mois' => $presencesDuMois,
                'de_l_annee' => $presencesDeLAnnee,
                'periode' => $presencesPeriode,
                'sur_periode' => $presencesPeriode,
            ],
            'acquisitions' => [
                'total_acquisitions_periode' => $acquisitionsPeriode,
                'sur_periode' => $acquisitionsPeriode,
                'quantite_totale' => $quantiteAcquise,
                'nombre_achats' => $acquisitionsAchats,
                'nombre_dons' => $acquisitionsDons,
                'montant_achats' => $montantAchats,
            ],
            'evolution_mensuelle' => $evolutionMensuelle,
            'top_livres' => $topLivres,
            'categories' => [
                'livres_par_categorie' => $livresParCategorie,
                'emprunts_par_categorie' => $empruntsParCategorie,
            ],
            'dernieres_activites' => $dernieresActivites,
        ];
    }
}
