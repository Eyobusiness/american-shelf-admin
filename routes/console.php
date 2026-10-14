<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Planification automatique des alertes et vérifications de bibliothèque
Schedule::command('library:check-overdue-loans')
    ->dailyAt('08:00')
    ->name('check_overdue_loans')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/overdue_loans.log'));

Schedule::command('library:check-expired-reservations')
    ->hourly()
    ->name('check_expired_reservations')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/expired_reservations.log'));

Schedule::command('inventaire:snapshot')
    ->monthlyOn(1, '01:00')
    ->name('inventaire_monthly_snapshot')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/inventaire_snapshot.log'));