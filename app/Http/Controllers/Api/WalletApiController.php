<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\CinetPayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletApiController extends Controller
{
    protected CinetPayService $cinetPay;

    public function __construct(CinetPayService $cinetPay)
    {
        $this->cinetPay = $cinetPay;
    }

    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'amount_xof' => 'required|integer|min:500|max:500000',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:10',
            'return_url' => 'required|url',
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            [
                'id' => Str::uuid()->toString(),
                'role' => 'client',
                'balance_xof' => 0,
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? 'BF',
                'lang' => 'fr',
                'created_at' => now(),
            ]
        );

        $depositId = Str::uuid()->toString();
        $reference = 'DEP-' . strtoupper(Str::random(8));

        $deposit = WalletDeposit::create([
            'id' => $depositId,
            'reference' => $reference,
            'user_id' => $user->id,
            'amount_xof' => $validated['amount_xof'],
            'customer_phone' => $validated['phone'] ?? null,
            'customer_country' => $validated['country'] ?? 'BF',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $checkout = $this->cinetPay->createPaymentPage([
                'order_id' => $deposit->id,
                'reference' => $deposit->reference,
                'amount_xof' => $validated['amount_xof'],
                'description' => "Recharge de solde — {$deposit->reference}",
                'email' => $user->email,
                'country' => $validated['country'] ?? 'BF',
                'return_url' => $validated['return_url'],
            ]);

            $deposit->update([
                'payment_checkout_id' => $checkout['transaction_id'] ?? null,
                'payment_intent_id' => $checkout['merchant_transaction_id'] ?? $deposit->reference,
                'notify_token' => $checkout['notify_token'] ?? null,
                'payment_url' => $checkout['redirect_url'],
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'deposit_id' => $deposit->id,
                'reference' => $deposit->reference,
                'payment_url' => $checkout['redirect_url'],
            ]);
        } catch (Exception $e) {
            $deposit->update([
                'status' => 'failed',
                'failure_message' => substr($e->getMessage(), 0, 300),
                'updated_at' => now(),
            ]);

            return response()->json([
                'error' => "Le paiement de la recharge n'a pas pu être initialisé."
            ], 500);
        }
    }

    public function status($id)
    {
        $deposit = WalletDeposit::findOrFail($id);

        if ($deposit->status === 'completed' && $deposit->credited_at) {
            return response()->json(['success' => true, 'deposit' => $deposit]);
        }

        try {
            $queryKey = $deposit->payment_intent_id ?: $deposit->payment_checkout_id ?: $deposit->reference ?: $deposit->id;
            $state = $this->cinetPay->getPayment($queryKey, $deposit->customer_country);

            if (!empty($state['is_successful']) || in_array(strtoupper($state['status'] ?? ''), ['COMPLETED', 'SUCCESS'])) {
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
                            'kind' => 'deposit',
                            'description' => "Recharge CinetPay — {$deposit->reference}",
                            'reference_type' => 'wallet_deposit',
                            'reference_id' => $deposit->id,
                            'created_at' => now(),
                        ]);
                    }

                    $locked->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                        'credited_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
            }

            return response()->json([
                'success' => true,
                'status' => $deposit->fresh()->status,
                'deposit' => $deposit->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => $deposit->status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
