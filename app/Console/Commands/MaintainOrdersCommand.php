<?php

namespace App\Console\Commands;

use App\Services\OrderFulfillmentService;
use Illuminate\Console\Command;

class MaintainOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:maintain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rattrape les webhooks manqués, expire les commandes impayées et relance les livraisons';

    /**
     * Execute the console command.
     */
    public function handle(OrderFulfillmentService $service): int
    {
        $this->info('Démarrage de la maintenance des commandes...');
        $report = $service->runOrderMaintenance();

        $this->line(" - Paiements vérifiés: {$report['payments']}");
        $this->line(" - Livraisons traitées: {$report['fulfilments']}");
        $this->line(" - Activations vérifiées: {$report['activations']}");
        $this->line(" - Commandes expirées: {$report['expired']}");
        $this->info('Maintenance terminée avec succès.');

        return Command::SUCCESS;
    }
}
