<?php

namespace App\Console\Commands;

use App\Models\Operation;
use App\Models\Site;
use App\Services\Usager\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckOverdueLoansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:check-overdue-loans {--site= : ID d\'un site spécifique}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Détecte les emprunts en retard, met à jour leur statut et déclenche les alertes (in-app et e-mail)';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Vérification des emprunts en retard en cours...');

        $siteFilter = $this->option('site');

        $query = Operation::with(['site.configuration', 'usager', 'exemplaire.livre'])
            ->where('type', 'EMPRUNT')
            ->whereIn('statut', ['EN_COURS', 'EN_RETARD'])
            ->whereNull('date_retour_effective')
            ->whereNotNull('date_retour_prevue');

        if ($siteFilter) {
            $query->where('site_id', $siteFilter);
        }

        $operations = $query->get();
        $this->line("Nombre d'emprunts actifs analysés : " . $operations->count());

        $overdueCount = 0;
        $alertsSent = 0;

        foreach ($operations as $operation) {
            $graceDays = (int) ($operation->site?->configuration?->nombre_jours_grace ?? 0);
            $dateLimite = Carbon::parse($operation->date_retour_prevue)->addDays($graceDays);

            if (now()->isAfter($dateLimite)) {
                $overdueCount++;

                // Mettre à jour le statut en EN_RETARD si nécessaire
                if ($operation->statut !== 'EN_RETARD') {
                    $operation->update(['statut' => 'EN_RETARD']);
                }

                // Déclencher la notification avec prévention des doublons
                $result = $notificationService->notifyEmpruntEnRetard($operation);
                if ($result !== null) {
                    $alertsSent++;
                    $titre = $operation->exemplaire?->livre?->titre ?? 'Livre inconnu';
                    $usagerNom = $operation->usager ? "{$operation->usager->prenom} {$operation->usager->nom}" : 'Inconnu';
                    $this->line(" -> Alerte envoyée pour: [{$titre}] emprunté par [{$usagerNom}]");
                }
            }
        }

        $this->info("Traitement terminé : {$overdueCount} emprunt(s) en retard détecté(s), {$alertsSent} nouvelle(s) alerte(s) générée(s).");

        return Command::SUCCESS;
    }
}