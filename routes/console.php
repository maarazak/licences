<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Planification automatique de la maintenance des commandes (toutes les 5 minutes)
Schedule::command('orders:maintain')->everyFiveMinutes();
