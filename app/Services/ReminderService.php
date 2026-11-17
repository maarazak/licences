<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReminderService
{
    public const MIN_GAP_BETWEEN_REMINDERS_HOURS = 6;

    /**
     * Traite les relances de paiement pour les commandes inachevées.
     * Maximum 2 relances par commande :
     *  - Étape 1 : 20 à 50 min après la commande (session encore ouverte)
     *  - Étape 2 : 90 min à 24 h après (proposer de recommencer)
     */
    public function processReminders(): array
    {
        $now = now();
        $report = ['stage1' => 0, 'stage2' => 0];
        $mailService = app(MailService::class);
        $fulfillment = app(OrderFulfillmentService::class);

        // Étape 1 : Commandes pending_payment créées il y a entre 20 et 50 minutes sans relance
        $stage1Orders = Order::with(['user', 'product'])
            ->where('status', 'pending_payment')
            ->where('reminder_count', 0)
            ->where('created_at', '<=', $now->copy()->subMinutes(20))
            ->where('created_at', '>=', $now->copy()->subMinutes(50))
            ->whereHas('user', function ($q) {
                $q->where('is_blocked', false)
                  ->where('mail_reminders', true);
            })
            ->limit(20)
            ->get();

        foreach ($stage1Orders as $order) {
            $user = $order->user;
            if (!$user) continue;

            // Re-vérifier l'état CinetPay avant de relancer (s'il vient d'être payé, ne pas relancer)
            try {
                $fresh = $fulfillment->syncPayment($order->id);
                if ($fresh && $fresh->status !== 'pending_payment') {
                    continue;
                }
            } catch (\Throwable $e) {}

            if ($this->hasFollowUp($order) || $this->remindedRecently($order->user_id, $order->id)) {
                continue;
            }

            // Réservation atomique
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
                $lang = $user->lang ?? 'fr';
                $slug = $order->product?->slug ?: Str::slug($order->product_name);
                $mailSent = $mailService->sendPaymentReminder($order, 1, $slug, $lang);

                if (!$mailSent) {
                    // Libération de la réservation en cas d'échec d'envoi
                    Order::where('id', $order->id)->update(['reminder_count' => 0, 'last_reminder_at' => null]);
                    continue;
                }

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'reminder',
                    'message' => 'Premier e-mail de relance paiement envoyé.',
                    'actor' => 'system',
                ]);
                $report['stage1']++;
            } catch (\Throwable $e) {
                Log::warning("[ReminderService] Erreur relance 1 pour {$order->reference}: {$e->getMessage()}");
            }
        }

        // Étape 2 : Commandes avec 1 relance, créées il y a entre 90 min et 24h
        $stage2Orders = Order::with(['user', 'product'])
            ->whereIn('status', ['pending_payment', 'payment_failed', 'expired'])
            ->where('reminder_count', 1)
            ->where('created_at', '<=', $now->copy()->subMinutes(90))
            ->where('created_at', '>=', $now->copy()->subHours(24))
            ->whereHas('user', function ($q) {
                $q->where('is_blocked', false)
                  ->where('mail_reminders', true);
            })
            ->limit(20)
            ->get();

        foreach ($stage2Orders as $order) {
            $user = $order->user;
            if (!$user) continue;

            if ($this->hasFollowUp($order) || $this->remindedRecently($order->user_id, $order->id)) {
                continue;
            }

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
                $lang = $user->lang ?? 'fr';
                $slug = $order->product?->slug ?: Str::slug($order->product_name);
                $mailSent = $mailService->sendPaymentReminder($order, 2, $slug, $lang);

                if (!$mailSent) {
                    Order::where('id', $order->id)->update(['reminder_count' => 1, 'last_reminder_at' => null]);
                    continue;
                }

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'reminder',
                    'message' => 'Second e-mail de relance paiement envoyé.',
                    'actor' => 'system',
                ]);
                $report['stage2']++;
            } catch (\Throwable $e) {
                Log::warning("[ReminderService] Erreur relance 2 pour {$order->reference}: {$e->getMessage()}");
            }
        }

        return $report;
    }

    protected function remindedRecently(string $userId, string $exceptOrderId): bool
    {
        return Order::where('user_id', $userId)
            ->where('id', '!=', $exceptOrderId)
            ->where('last_reminder_at', '>=', now()->subHours(self::MIN_GAP_BETWEEN_REMINDERS_HOURS))
            ->exists();
    }

    protected function hasFollowUp(Order $order): bool
    {
        return Order::where('user_id', $order->user_id)
            ->where('product_id', $order->product_id)
            ->where('id', '!=', $order->id)
            ->where(function ($query) use ($order) {
                $query->where(function ($q) use ($order) {
                    $q->where('created_at', '>=', $order->created_at)
                      ->whereIn('status', ['pending_payment', 'paid', 'processing', 'activation_awaiting', 'delivered']);
                })->orWhere(function ($q) {
                    $q->where('created_at', '>=', now()->subDay())
                      ->whereIn('status', ['paid', 'processing', 'activation_awaiting', 'delivered']);
                });
            })
            ->exists();
    }
}
