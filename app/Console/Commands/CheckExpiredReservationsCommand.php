<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Usager\ReservationService;
use Illuminate\Console\Command;

class CheckExpiredReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:check-expired-reservations {--site= : ID d\'un site spécifique}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifie et clôture les réservations ayant dépassé leur date d\'expiration';

    /**
     * Execute the console command.
     */
    public function handle(ReservationService $reservationService): int
    {
        $this->info('Vérification des réservations expirées en cours...');

        $siteFilter = $this->option('site');
        $sites = $siteFilter ? Site::where('id', $siteFilter)->get() : Site::all();

        $totalExpired = 0;

        foreach ($sites as $site) {
            $count = $reservationService->updateExpiredReservations($site->id);
            $totalExpired += $count;
            if ($count > 0) {
                $this->line(" -> Site [{$site->nom}] : {$count} réservation(s) expirée(s) clôturée(s).");
            }
        }

        $this->info("Traitement terminé : {$totalExpired} réservation(s) expirée(s) au total.");

        return Command::SUCCESS;
    }
}