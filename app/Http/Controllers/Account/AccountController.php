<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletEntry;
use App\Services\CryptoService;
use App\Services\CinetPayService;
use App\Services\NowPaymentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AccountController extends Controller
{
    protected function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function dashboard()
    {
        $user = $this->user();

        $recentOrders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $openTicketsCount = Ticket::where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->count();

        $alertsCount = StockAlert::where('user_id', $user->id)->count();

        return Inertia::render('Account/Dashboard', [
            'recentOrders' => $recentOrders,
            'openTicketsCount' => $openTicketsCount,
            'alertsCount' => $alertsCount,
        ]);
    }

    public function orders()
    {
        $user = $this->user();

        $orders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Account/Orders', [
            'orders' => $orders,
        ]);
    }

    public function accessList()
    {
        $user = $this->user();

        $deliveredOrders = Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->orderBy('delivered_at', 'desc')
            ->get()
            ->map(function ($order) {
                $items = [];
                if ($order->delivery_encrypted) {
                    $raw = CryptoService::decrypt($order->delivery_encrypted, $order->id);
                    if ($raw) {
                        $parsed = json_decode($raw, true);
                        if (is_array($parsed)) {
                            $items = $parsed;
                        } else {
                            $items = [$raw];
                        }
                    }
                }
                return [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'product_name' => $order->product_name,
                    'delivered_at' => $order->delivered_at ? $order->delivered_at->toIso8601String() : null,
                    'items' => $items,
                ];
            });

        return Inertia::render('Account/Access', [
            'orders' => $deliveredOrders->all(),
        ]);
    }

    public function orderDetail($id)
    {
        $user = $this->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['events' => fn ($q) => $q->orderBy('created_at', 'asc')])
            ->firstOrFail();

        $decryptedDelivery = null;
        if ($order->delivery_encrypted) {
            $decryptedDelivery = CryptoService::decrypt($order->delivery_encrypted, $order->id);
        }

        return Inertia::render('Account/OrderShow', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'total_xof' => $order->total_xof,
                'status' => $order->status,
                'paid_with' => $order->paid_with,
                'refund_requested_at' => $order->refund_requested_at ? $order->refund_requested_at->toIso8601String() : null,
                'refund_method' => $order->refund_method,
                'can_resolve' => false,
                'customer_phone' => $order->customer_phone,
                'customer_country' => $order->customer_country,
                'delivery_content' => $decryptedDelivery,
                'delivered_at' => $order->delivered_at ? $order->delivered_at->toIso8601String() : null,
                'paid_at' => $order->paid_at ? $order->paid_at->toIso8601String() : null,
                'created_at' => $order->created_at ? $order->created_at->toIso8601String() : now()->toIso8601String(),
                'payment_url' => $order->payment_url,
                'events' => $order->events->map(function ($e) use ($order) {
                    $cleanMsg = $e->message ?: '';
                    // Anonymisation stricte : masquer tout terme fournisseur ou message technique brut
                    $cleanMsg = preg_replace('/(Flowseller|pe-supplykh|fournisseur)/i', 'service technique', $cleanMsg);
                    $cleanMsg = preg_replace('/(clé API.*|API key.*)/i', 'configuration technique.', $cleanMsg);
                    if (str_contains($cleanMsg, 'Livraison impossible (')) {
                        $cleanMsg = "Commande prise en charge par notre équipe pour régularisation.";
                    }
                    return [
                        'id' => $e->id,
                        'event' => $e->event ?: $e->type,
                        'message' => $cleanMsg,
                        'created_at' => $e->created_at ? $e->created_at->toIso8601String() : ($order->created_at ? $order->created_at->toIso8601String() : now()->toIso8601String()),
                    ];
                }),
            ],
        ]);
    }

    public function chooseCredit($id)
    {
        abort(403, "Seul le service administratif ou le back-office peut attribuer un avoir pour cette commande.");
    }

    public function requestRefund($id)
    {
        abort(403, "Seul le service administratif ou le back-office peut traiter un remboursement pour cette commande.");
    }

    public function wallet(Request $request)
    {
        $user = $this->user();

        $entries = WalletEntry::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Account/Wallet', [
            'defaultCountry' => \App\Support\CountryDetector::preselect($request, $user->country),
            'entries' => $entries,
        ]);
    }

    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount_xof' => 'required|integer|min:500|max:1000000',
            'phone' => 'nullable|string|max:30',
            'country' => ['nullable', 'string', 'max:5', \Illuminate\Validation\Rule::in(CinetPayService::PAYABLE_COUNTRIES)],
            'fullName' => 'nullable|string|max:120',
            'gateway' => 'nullable|string|in:cinetpay,nowpayments,crypto',
        ]);

        $gateway = in_array($validated['gateway'] ?? '', ['nowpayments', 'crypto'], true) ? 'nowpayments' : 'cinetpay';
        $user = $this->user();
        $depositId = Str::uuid()->toString();
        $ref = 'DEP-' . strtoupper(Str::random(8));

        // 1. Rechargement en crypto-monnaies via NOWPayments (USDT, BTC, ETH, SOL, TON, LTC…)
        if ($gateway === 'nowpayments') {
            try {
                $returnUrl = url("/paiement/retour?depot={$depositId}");

                $session = app(NowPaymentsService::class)->createInvoice([
                    'order_id' => $ref,
                    'amount_xof' => $validated['amount_xof'],
                    'return_url' => $returnUrl,
                    'description' => "Recharge de solde {$ref}",
                ]);

                $redirectUrl = $session['payment_url'] ?? null;
                if (!$redirectUrl) {
                    throw new \Exception("URL de paiement introuvable.");
                }

                WalletDeposit::create([
                    'id' => $depositId,
                    'reference' => $ref,
                    'user_id' => $user->id,
                    'amount_xof' => $validated['amount_xof'],
                    'customer_phone' => $validated['phone'] ?? null,
                    'customer_country' => $validated['country'] ?? ($user->country ?: 'BF'),
                    'customer_name' => $validated['fullName'] ?? trim("{$user->first_name} {$user->last_name}") ?: null,
                    'status' => 'pending',
                    'gateway' => 'nowpayments',
                    'payment_checkout_id' => !empty($session['invoice_id']) ? 'inv:' . $session['invoice_id'] : null,
                    'payment_intent_id' => $ref,
                    'payment_url' => $redirectUrl,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                    return response()->json([
                        'success' => true,
                        'seamless' => false,
                        'deposit_id' => $depositId,
                        'reference' => $ref,
                        'payment_url' => $redirectUrl,
                        'return_url' => $returnUrl,
                    ]);
                }

                return Inertia::location($redirectUrl);
            } catch (\Throwable $e) {
                \Log::error("Erreur deposit NOWPayments : " . $e->getMessage());
                $genericError = "Le service de recharge est temporairement indisponible. Si le problème persiste, veuillez contacter le service client.";

                if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                    return response()->json([
                        'success' => false,
                        'error' => $genericError,
                    ], 500);
                }

                return back()->with('error', $genericError);
            }
        }

        // 2. Rechargement par CinetPay (Mobile Money & Cartes Bancaires)
        try {
            $cinetPay = app(CinetPayService::class);
            $returnUrl = url("/paiement/retour?depot={$depositId}");

            $session = $cinetPay->createPaymentPage([
                'order_id' => $depositId,
                'reference' => $ref,
                'amount_xof' => $validated['amount_xof'],
                'country' => $validated['country'] ?? ($user->country ?: 'BF'),
                'email' => $user->email,
                'fullName' => $validated['fullName'] ?? trim("{$user->first_name} {$user->last_name}") ?: null,
                'return_url' => $returnUrl,
                'description' => "Recharge de solde ({$validated['amount_xof']} FCFA)",
                'lang' => $user->lang ?? 'fr',
            ]);

            $redirectUrl = $session['redirect_url'] ?? $session['redirectUrl'] ?? $session['payment_url'] ?? null;

            if (!$redirectUrl) {
                throw new \Exception("URL de paiement introuvable.");
            }

            WalletDeposit::create([
                'id' => $depositId,
                'reference' => $ref,
                'user_id' => $user->id,
                'amount_xof' => $validated['amount_xof'],
                'customer_phone' => $validated['phone'] ?? null,
                'customer_country' => $validated['country'],
                'customer_name' => $validated['fullName'] ?? trim("{$user->first_name} {$user->last_name}") ?: null,
                'status' => 'pending',
                'payment_checkout_id' => $session['transaction_id'] ?? null,
                'payment_intent_id' => $session['merchant_transaction_id'] ?? $ref,
                'notify_token' => $session['notify_token'] ?? null,
                'payment_url' => $redirectUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mémorise les coordonnées du client pour les futurs achats
            $userUpdates = [];
            if (!empty($validated['phone']) && !$user->phone) $userUpdates['phone'] = $validated['phone'];
            if (!empty($validated['country']) && !$user->country) $userUpdates['country'] = $validated['country'];
            if (!empty($userUpdates)) $user->update($userUpdates);

            if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'seamless' => true,
                    'deposit_id' => $depositId,
                    'reference' => $ref,
                    'payment_token' => $session['payment_token'] ?? null,
                    'payment_url' => $redirectUrl,
                    'return_url' => $returnUrl,
                ]);
            }

            return Inertia::location($redirectUrl);
        } catch (\Throwable $e) {
            \Log::error("Erreur deposit CinetPay : " . $e->getMessage());
            $genericError = "Le service de recharge est temporairement indisponible. Si le problème persiste, veuillez contacter le service client.";

            if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'error' => $genericError,
                ], 422);
            }

            return back()->with('error', $genericError);
        }
    }

    public function tickets()
    {
        $user = $this->user();

        $tickets = Ticket::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Account/Tickets', [
            'tickets' => $tickets,
        ]);
    }

    public function newTicket(Request $request)
    {
        $orderId = $request->query('commande');
        $user = $this->user();

        $order = null;
        if ($orderId) {
            $order = Order::where('id', $orderId)->where('user_id', $user->id)->first();
        }

        return Inertia::render('Account/TicketCreate', [
            'order' => $order ? [
                'id' => $order->id,
                'reference' => $order->reference,
                'product_name' => $order->product_name,
            ] : null,
        ]);
    }

    public function createTicket(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        $user = $this->user();
        $ticketId = Str::uuid()->toString();

        $orderId = $validated['order_id'] ?? null;
        if ($orderId) {
            $userOwnsOrder = Order::where('id', $orderId)->where('user_id', $user->id)->exists();
            if (!$userOwnsOrder) {
                $orderId = null;
            }
        }

        DB::transaction(function () use ($user, $validated, $ticketId, $orderId) {
            $ticket = Ticket::create([
                'id' => $ticketId,
                'reference' => 'TIC-' . strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'order_id' => $orderId,
                'subject' => $validated['subject'],
                'status' => 'open',
                'created_at' => now(),
            ]);

            TicketMessage::create([
                'id' => Str::uuid()->toString(),
                'ticket_id' => $ticket->id,
                'author_id' => $user->id,
                'body' => $validated['message'],
                'is_staff' => false,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('account.tickets.show', $ticketId)->with('success', 'Votre ticket a été créé avec succès.');
    }

    public function showTicket($id)
    {
        $user = $this->user();

        $ticket = Ticket::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at', 'asc')])
            ->firstOrFail();

        return Inertia::render('Account/TicketShow', [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->toIso8601String(),
                'messages' => $ticket->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'message' => $m->body,
                    'is_staff' => (bool) $m->is_staff,
                    'created_at' => $m->created_at ? $m->created_at->toIso8601String() : now()->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function replyTicket(Request $request, $id)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $user = $this->user();
        $ticket = Ticket::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        TicketMessage::create([
            'id' => Str::uuid()->toString(),
            'ticket_id' => $ticket->id,
            'author_id' => $user->id,
            'body' => $validated['message'],
            'is_staff' => false,
            'created_at' => now(),
        ]);

        $ticket->update(['status' => 'open']);

        return back()->with('success', 'Votre réponse a été envoyée.');
    }

    public function alerts()
    {
        $user = $this->user();

        $alerts = StockAlert::where('user_id', $user->id)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Account/Alerts', [
            'alerts' => $alerts,
        ]);
    }

    public function createAlert(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = $this->user();

        StockAlert::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
        ], [
            'id' => Str::uuid()->toString(),
            'email' => $user->email,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Vous serez alerté dès le réapprovisionnement.');
    }

    public function deleteAlert($id)
    {
        $user = $this->user();
        StockAlert::where('id', $id)->where('user_id', $user->id)->delete();

        return back()->with('success', 'Alerte supprimée.');
    }

    public function profile()
    {
        $user = $this->user();

        return Inertia::render('Account/Profile', [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'country' => $user->country ?? 'BF',
                'lang' => $user->lang ?? 'fr',
                'mail_reminders' => (bool) $user->mail_reminders,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:5',
            'lang' => 'nullable|in:fr,en',
            'mail_reminders' => 'nullable|boolean',
        ]);

        $user = $this->user();
        $user->update([
            'first_name' => $validated['first_name'] ?? $user->first_name,
            'last_name' => $validated['last_name'] ?? $user->last_name,
            'phone' => $validated['phone'] ?? $user->phone,
            'country' => $validated['country'] ?? $user->country,
            'lang' => $validated['lang'] ?? $user->lang,
            'mail_reminders' => $request->boolean('mail_reminders'),
        ]);

        return back()->with('success', 'Vos modifications ont été enregistrées.');
    }

    public function logoutEverywhere(Request $request)
    {
        $user = $this->user();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Tous vos appareils ont été déconnectés.');
    }
}
