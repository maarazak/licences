<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\PawaPayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    protected PawaPayService $pawaPay;

    public function __construct(PawaPayService $pawaPay)
    {
        $this->pawaPay = $pawaPay;
    }

    public function pawapay(Request $request)
    {
        $depositId = $request->input('depositId');
        if (!$depositId) {
            return response()->json(['error' => 'Missing depositId'], 400);
        }

        Log::info("[Webhook pawaPay] Notification reçue pour deposit {$depositId}");

        // Relecture sécurisée auprès de l'API (règle de sécurité pawaPay)
        try {
            $state = $this->pawaPay->getDeposit($depositId);
        } catch (Exception $e) {
            Log::error("[Webhook pawaPay] Relecture échouée: {$e->getMessage()}");
            return response()->json(['status' => 'retry_later'], 500);
        }

        // 1. Recherche dans les commandes
        $order = Order::where('payment_checkout_id', $depositId)
            ->orWhere('id', $depositId)
            ->first();

        if ($order) {
            $this->syncOrder($order, $state);
            return response()->json(['status' => 'order_synced']);
        }

        // 2. Recherche dans les recharges de solde
        $deposit = WalletDeposit::where('payment_checkout_id', $depositId)
            ->orWhere('id', $depositId)
            ->first();

        if ($deposit) {
            $this->syncDeposit($deposit, $state);
            return response()->json(['status' => 'deposit_synced']);
        }

        return response()->json(['status' => 'not_found'], 404);
    }

    protected function syncOrder(Order $order, array $state): void
    {
        if ($order->status === 'delivered' || $order->status === 'refunded') {
            return;
        }

        if ($state['payment_status'] === 'completed') {
            $amountOk = $state['amount'] === $order->total_xof &&
                (empty($state['currency']) || in_array(strtoupper($state['currency']), ['XOF', 'XAF']));

            if ($amountOk && $order->status === 'pending_payment') {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

                OrderEvent::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'type' => 'paid',
                    'message' => "Paiement de {$order->total_xof} FCFA confirmé via pawaPay.",
                    'actor' => 'pawapay',
                    'created_at' => now(),
                ]);
            }
        } elseif ($state['payment_status'] === 'failed' && $order->status === 'pending_payment') {
            $order->update([
                'status' => 'cancelled',
                'failure_code' => $state['failure_code'] ?? 'payment_failed',
                'failure_message' => $state['failure_message'] ?? 'Paiement échoué',
                'updated_at' => now(),
            ]);
        }
    }

    protected function syncDeposit(WalletDeposit $deposit, array $state): void
    {
        if ($deposit->status === 'completed' && $deposit->credited_at) {
            return;
        }

        if ($state['payment_status'] === 'completed') {
            $amountOk = $state['amount'] === $deposit->amount_xof &&
                (empty($state['currency']) || in_array(strtoupper($state['currency']), ['XOF', 'XAF']));

            if ($amountOk) {
                DB::transaction(function () use ($deposit) {
                    $locked = WalletDeposit::lockForUpdate()->find($deposit->id);
                    if (!$locked || $locked->credited_at) return;

                    $user = User::lockForUpdate()->find($deposit->user_id);
                    if ($user) {
                        $newBalance = $user->balance_xof + $deposit->amount_xof;
                        $user->balance_xof = $newBalance;
                        $user->save();

                        WalletEntry::create([
                            'id' => Str::uuid()->toString(),
                            'user_id' => $user->id,
                            'amount_xof' => $deposit->amount_xof,
                            'balance_after_xof' => $newBalance,
                            'type' => 'credit',
                            'description' => "Recharge Mobile Money — {$deposit->reference}",
                            'reference_type' => 'wallet_deposit',
                            'reference_id' => $deposit->id,
                            'created_at' => now(),
                        ]);
                    }

                    $deposit->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                        'credited_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
            }
        }
    }
}
