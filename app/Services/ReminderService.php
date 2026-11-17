<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReminderService
{
    /**
     * Traite les relances de paiement pour les commandes inachevées.
     * Maximum 2 relances par commande :
     *  - Étape 1 : 20 à 50 min après la commande (session encore ouverte)
     *  - Étape 2 : 90 min à 24 h après (proposer de recommencer)
     */
    public function processReminders(): int
    {
        $now = now();
        $sent = 0;
        $mailService = app(MailService::class);

        // Étape 1 : Commandes pending_payment créées il y a entre 20 et 50 minutes sans relance
        $stage1Orders = Order::where('status', 'pending_payment')
            ->where('reminder_count', 0)
            ->where('created_at', '<=', $now->copy()->subMinutes(20))
            ->where('created_at', '>=', $now->copy()->subMinutes(50))
            ->limit(20)
            ->get();

        foreach ($stage1Orders as $order) {
            $claimed = Order::where('id', $order->id)
                ->where('reminder_count', 0)
                ->update([
                    'reminder_count' => 1,
                    'last_reminder_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                // Notifier par e-mail
                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'reminder',
                    'message' => 'Premier e-mail de relance paiement envoyé.',
                    'actor' => 'system',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("[ReminderService] Erreur relance 1 pour {$order->reference}: {$e->getMessage()}");
            }
        }

        // Étape 2 : Commandes avec 1 relance, créées il y a entre 90 min et 24h
        $stage2Orders = Order::whereIn('status', ['pending_payment', 'payment_failed', 'expired'])
            ->where('reminder_count', 1)
            ->where('created_at', '<=', $now->copy()->subMinutes(90))
            ->where('created_at', '>=', $now->copy()->subHours(24))
            ->limit(20)
            ->get();

        foreach ($stage2Orders as $order) {
            $claimed = Order::where('id', $order->id)
                ->where('reminder_count', 1)
                ->update([
                    'reminder_count' => 2,
                    'last_reminder_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'reminder',
                    'message' => 'Second e-mail de relance paiement envoyé.',
                    'actor' => 'system',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("[ReminderService] Erreur relance 2 pour {$order->reference}: {$e->getMessage()}");
            }
        }

        return $sent;
    }
}
