<?php

namespace App\Console\Commands;

use App\Services\SchedulerService;
use Illuminate\Console\Command;

class CronTickCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exécute la tâche planifiée d\'une minute (orders, catalog sync, balance, alerts, reminders, purge)';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $scheduler): int
    {
        $this->info('Exécution de la tâche planifiée cron:tick...');
        $report = $scheduler->runTick();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info('Tâche terminée.');
        return Command::SUCCESS;
    }
}
