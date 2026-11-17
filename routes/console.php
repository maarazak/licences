<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tâche planifiée principale (chaque minute) : commandes, synchro catalogue, solde, alertes, relances, purge
Schedule::command('cron:tick')->everyMinute()->withoutOverlapping();
