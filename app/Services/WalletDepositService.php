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

    /** Relit l'état d'une recharge crypto chez NOWPayments (identifiant de paiement connu après le premier IPN). */
    public function syncCrypto(WalletDeposit $deposit): WalletDeposit
    {
        $paymentId = (string) $deposit->payment_checkout_id;
        if ($deposit->gateway !== 'nowpayments' || $deposit->status !== 'pending' || !ctype_digit($paymentId)) {
            return $deposit;
        }

        try {
            $verified = app(NowPaymentsService::class)->getPayment($paymentId);
            if (empty($verified['error'])) {
                $this->settleCryptoPayment($deposit, $verified);
            }
        } catch (\Throwable $e) {
            Log::warning("[Wallet] Vérification NOWPayments impossible pour {$deposit->reference}: {$e->getMessage()}");
        }

        return $deposit->fresh() ?? $deposit;
    }

    /**
     * Recharge payée en crypto, d'après l'état RELU chez NOWPayments. Crédit unique : verrou de ligne, statut « pending » et
     * `credited_at`. Retourne : credited | failed | underpaid | ignored.
     */
    public function settleCryptoPayment(WalletDeposit $deposit, array $verified): string
    {
        if (($verified['order_id'] ?? '') === '' || !hash_equals((string) $deposit->reference, (string) $verified['order_id'])) {
            Log::warning("[Wallet] Réponse NOWPayments sans correspondance avec la recharge {$deposit->reference}.");
            return 'ignored';
        }

        $status = (string) ($verified['payment_status'] ?? '');

        if ($status === 'finished') {
            if (app(NowPaymentsService::class)->isUnderpaid((int) $deposit->amount_xof, $verified)) {
                $moved = WalletDeposit::where('id', $deposit->id)->where('status', 'pending')->update([
                    'status' => 'failed',
                    'failure_message' => 'Montant ou devise reçu différent du montant dû : aucun crédit, contactez l\'équipe.',
                    'updated_at' => now(),
                ]);
                if ($moved === 1) {
                    Log::error("[Wallet] Montant crypto inférieur au dû pour la recharge {$deposit->reference}.");
                }
                return 'underpaid';
            }

            return DB::transaction(function () use ($deposit, $verified) {
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

                $coin = ($verified['pay_currency'] ?? '') ?: 'Crypto';
                $network = !empty($verified['network']) ? " ({$verified['network']})" : '';
                WalletEntry::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user->id,
                    'amount_xof' => $locked->amount_xof,
                    'balance_after_xof' => $newBalance,
                    'type' => 'credit',
                    'kind' => 'deposit',
                    'description' => "Recharge Crypto [{$coin}{$network}] — {$locked->reference}",
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

        if (in_array($status, ['failed', 'expired', 'refunded'], true)) {
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
