<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recharges de solde payées par CinetPay. Une seule voie pour créditer le solde : webhook, retour client et vérifications
 * passent toutes par `settleGatewayPayment`, qui ne crédite qu'une fois (verrou de ligne + `credited_at`).
 */
class WalletDepositService
{
    public function __construct(protected CinetPayService $cinetPay)
    {
    }

    /** Relit l'état chez CinetPay avec l'identifiant STOCKÉ de la recharge, puis applique le résultat. */
    public function sync(WalletDeposit $deposit): WalletDeposit
    {
        if ($deposit->status !== 'pending') {
            return $deposit;
        }

        try {
            $queryKey = $deposit->payment_intent_id ?: $deposit->payment_checkout_id ?: $deposit->reference ?: $deposit->id;
            $state = $this->cinetPay->getPayment($queryKey, $deposit->customer_country);
            if (empty($state['error'])) {
                $this->settleGatewayPayment($deposit, $state);
            }
        } catch (\Throwable $e) {
            Log::warning("[Wallet] Erreur vérification recharge {$deposit->reference}: {$e->getMessage()}");
        }

        return $deposit->fresh() ?? $deposit;
    }

    /**
     * Applique l'état d'un paiement relu auprès de l'API. Retourne : credited | failed | underpaid | ignored.
     */
    public function settleGatewayPayment(WalletDeposit $deposit, array $state): string
    {
        if (!CinetPayService::matchesRecord($deposit, $state)) {
            Log::warning("[Wallet] Réponse CinetPay sans correspondance avec la recharge {$deposit->reference}.");
            return 'ignored';
        }

        if (!empty($state['is_successful'])) {
            if (CinetPayService::isUnderpaid((int) $deposit->amount_xof, $state)) {
                $moved = WalletDeposit::where('id', $deposit->id)->where('status', 'pending')->update([
                    'status' => 'failed',
                    'failure_message' => 'Montant ou devise reçu différent du montant dû : aucun crédit, contactez l\'équipe.',
                    'updated_at' => now(),
                ]);
                if ($moved === 1) {
                    Log::error("[Wallet] Montant inférieur au dû pour la recharge {$deposit->reference}.");
                }
                return 'underpaid';
            }

            return DB::transaction(function () use ($deposit) {
                $locked = WalletDeposit::lockForUpdate()->find($deposit->id);
                if (!$locked || $locked->credited_at || $locked->status !== 'pending') {
                    return 'ignored';
                }

                $user = User::lockForUpdate()->find($locked->user_id);
                if (!$user) {
                    return 'ignored';
                }

                $newBalance = (int) $user->balance_xof + (int) $locked->amount_xof;
                $user->balance_xof = $newBalance;
                $user->save();

                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'amount_xof' => $locked->amount_xof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'credit',
                    'kind' => 'deposit',
                    'description' => "Recharge de solde — {$locked->reference}",
                    'reference_type' => 'wallet_deposit',
                    'reference_id' => $locked->id,
                    'created_at' => now(),
                ]);

                $locked->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'credited_at' => now(),
                    'updated_at' => now(),
                ]);

                return 'credited';
            });
        }

        if (!empty($state['is_final'])) {
            $moved = WalletDeposit::where('id', $deposit->id)->where('status', 'pending')->update([
                'status' => 'failed',
                'failure_message' => "Paiement non complété ({$state['status']})",
                'updated_at' => now(),
            ]);

            return $moved === 1 ? 'failed' : 'ignored';
        }

        return 'ignored';
    }

    /**
     * Recharge payée en crypto (webhook Cryptomus signé). Crédit unique : verrou de ligne, statut « pending » et `credited_at`.
     * Retourne : credited | failed | ignored.
     */
    public function settleCryptoPayment(WalletDeposit $deposit, array $payload): string
    {
        $reportedId = (string) ($payload['order_id'] ?? '');
        if ($reportedId !== '' && !in_array($reportedId, array_filter([(string) $deposit->reference, (string) $deposit->payment_intent_id]), true)) {
            Log::warning("[Wallet] Réponse Cryptomus sans correspondance avec la recharge {$deposit->reference}.");
            return 'ignored';
        }

        $status = strtolower((string) ($payload['status'] ?? $payload['payment_status'] ?? ''));

        if (in_array($status, ['paid', 'paid_over'], true)) {
            return DB::transaction(function () use ($deposit, $payload) {
                $locked = WalletDeposit::lockForUpdate()->find($deposit->id);
                if (!$locked || $locked->credited_at || $locked->status !== 'pending') {
                    return 'ignored';
                }

                $user = User::lockForUpdate()->find($locked->user_id);
                if (!$user) {
                    return 'ignored';
                }

                $newBalance = (int) $user->balance_xof + (int) $locked->amount_xof;
                $user->balance_xof = $newBalance;
                $user->save();

                $payerCurrency = $payload['payer_currency'] ?? $payload['currency'] ?? 'Crypto';
                $network = !empty($payload['network']) ? " ({$payload['network']})" : '';
                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'amount_xof' => $locked->amount_xof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'credit',
                    'kind' => 'deposit',
                    'description' => "Recharge Crypto [{$payerCurrency}{$network}] — {$locked->reference}",
                    'reference_type' => 'wallet_deposit',
                    'reference_id' => $locked->id,
                    'created_at' => now(),
                ]);

                $locked->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'credited_at' => now(),
                    'payment_checkout_id' => ($payload['uuid'] ?? null) ?: $locked->payment_checkout_id,
                    'updated_at' => now(),
                ]);

                return 'credited';
            });
        }

        if (in_array($status, ['cancel', 'fail', 'system_fail'], true)) {
            $moved = WalletDeposit::where('id', $deposit->id)->where('status', 'pending')->update([
                'status' => 'failed',
                'failure_message' => "Paiement crypto non complété ({$status})",
                'updated_at' => now(),
            ]);

            return $moved === 1 ? 'failed' : 'ignored';
        }

        return 'ignored';
    }
}
