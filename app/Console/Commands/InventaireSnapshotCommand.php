<?php

namespace App\Console\Commands;

use App\Models\CategorieLivre;
use App\Models\Exemplaire;
use App\Models\InventaireSnapshot;
use App\Models\Livre;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventaireSnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventaire:snapshot
                            {--site= : UUID ou code du site à traiter (optionnel)}
                            {--date= : Date de référence YYYY-MM-DD (optionnel, défaut aujourd\'hui)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère un snapshot périodique fidèle de l\'état de l\'inventaire par site';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $siteOption = $this->option('site');
        $dateOption = $this->option('date');

        try {
            $refDate = $dateOption ? Carbon::parse($dateOption) : now();
        } catch (\Exception $e) {
            $this->error("Format de date invalide pour --date. Utilisez le format AAAA-MM-JJ.");
            return self::FAILURE;
        }

        $query = Site::query();
        if ($siteOption) {
            $query->where(function ($q) use ($siteOption) {
                $q->where('id', $siteOption)->orWhere('code', $siteOption);
            });
        }

        $sites = $query->get();

        if ($sites->isEmpty()) {
            $this->warn("Aucun site trouvé pour la génération du snapshot.");
            return self::SUCCESS;
        }

        $this->info("Démarrage de la génération des snapshots d'inventaire pour {$sites->count()} site(s) à la date du {$refDate->toDateString()}...");

        foreach ($sites as $site) {
            $this->line("Calcul de l'inventaire pour le site : {$site->nom} ({$site->code})");

            $totalLivres = Livre::where('site_id', $site->id)->count();
            $totalExemplaires = Exemplaire::where('site_id', $site->id)->count();
            $totalDisponibles = Exemplaire::where('site_id', $site->id)->where('statut', 'DISPONIBLE')->count();
            $totalEmpruntes = Exemplaire::where('site_id', $site->id)->where('statut', 'EMPRUNTE')->count();
            $totalConsultation = Exemplaire::where('site_id', $site->id)->where('statut', 'EN_CONSULTATION')->count();
            $totalReserves = Exemplaire::where('site_id', $site->id)->where('statut', 'RESERVE')->count();
            $totalIndisponibles = Exemplaire::where('site_id', $site->id)->where('statut', 'INDISPONIBLE')->count();
            // Conformément à l'audit, 'PERDU' est un état physique
            $totalPerdus = Exemplaire::where('site_id', $site->id)->where('etat', 'PERDU')->count();

            // Répartition par état physique
            $repartitionEtats = Exemplaire::where('site_id', $site->id)
                ->select('etat', DB::raw('count(*) as aggregate'))
                ->groupBy('etat')
                ->pluck('aggregate', 'etat')
                ->toArray();

            // Répartition par statut opérationnel
            $repartitionStatuts = Exemplaire::where('site_id', $site->id)
                ->select('statut', DB::raw('count(*) as aggregate'))
                ->groupBy('statut')
                ->pluck('aggregate', 'statut')
                ->toArray();

            // Répartition par catégorie de livres
            $categories = CategorieLivre::where('site_id', $site->id)->get();
            $repartitionCategories = [];
            foreach ($categories as $cat) {
                $exemplairesCount = Exemplaire::where('site_id', $site->id)
                    ->whereHas('livre', fn($q) => $q->where('categorie_livre_id', $cat->id))
                    ->count();

                $repartitionCategories[$cat->nom] = $exemplairesCount;
            }

            // Enregistrement idempotent (updateOrCreate sur [site_id, date_snapshot])
            $snapshot = InventaireSnapshot::updateOrCreate(
                [
                    'site_id' => $site->id,
                    'date_snapshot' => $refDate->toDateString(),
                ],
                [
                    'annee' => $refDate->year,
                    'mois' => $refDate->month,
                    'total_livres' => $totalLivres,
                    'total_exemplaires' => $totalExemplaires,
                    'total_disponibles' => $totalDisponibles,
                    'total_empruntes' => $totalEmpruntes,
                    'total_consultation' => $totalConsultation,
                    'total_reserves' => $totalReserves,
                    'total_indisponibles' => $totalIndisponibles,
                    'total_perdus' => $totalPerdus,
                    'repartition_etats' => $repartitionEtats,
                    'repartition_statuts' => $repartitionStatuts,
                    'repartition_categories' => $repartitionCategories,
                    'metadata' => [
                        'generated_by' => 'cli:inventaire:snapshot',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]
            );

            $this->info("✓ Snapshot enregistré pour {$site->code} (ID: {$snapshot->id}) - Livres: {$totalLivres}, Exemplaires: {$totalExemplaires}.");
        }

        $this->info("Génération terminée avec succès.");
        return self::SUCCESS;
    }
}
