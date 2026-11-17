<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\PawaPayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletApiController extends Controller
{
    protected PawaPayService $pawaPay;

    public function __construct(PawaPayService $pawaPay)
    {
        $this->pawaPay = $pawaPay;
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
            $checkout = $this->pawaPay->createPaymentPage([
                'deposit_id' => $deposit->id,
                'order_id' => $deposit->id,
                'reference' => $deposit->reference,
                'amount_xof' => $validated['amount_xof'],
                'description' => "Recharge de solde — {$deposit->reference}",
                'email' => $user->email,
                'phone' => $validated['phone'] ?? null,
                'country' => $validated['country'] ?? 'BF',
                'return_url' => $validated['return_url'],
            ]);

            $deposit->update([
                'payment_checkout_id' => $checkout['deposit_id'],
                'payment_intent_id' => $checkout['deposit_id'],
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
                'failure_code' => 'checkout_failed',
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
            $state = $this->pawaPay->getDeposit($deposit->payment_checkout_id ?? $deposit->id);

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
            } elseif ($state['payment_status'] === 'failed') {
                $deposit->update([
                    'status' => 'failed',
                    'failure_code' => $state['failure_code'] ?? 'payment_failed',
                    'failure_message' => $state['failure_message'] ?? 'Paiement échoué',
                    'updated_at' => now(),
                ]);
            }
        } catch (Exception $e) {
            // Keep pending
        }

        return response()->json([
            'success' => true,
            'deposit' => $deposit->fresh(),
        ]);
    }
}
