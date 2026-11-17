@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <nav aria-label="Fil d'Ariane" class="text-xs text-muted">
        <a href="{{ route('admin.orders.index') }}" class="hover:text-ink">
            Commandes
        </a>
        / <span class="text-ink">{{ $order->reference }}</span>
    </nav>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="h1">{{ $order->reference }}</h1>
        @php
            $statusTones = [
                'pending_payment' => 'neutral',
                'paid' => 'info',
                'processing' => 'info',
                'activation_awaiting' => 'info',
                'delivered' => 'success',
                'failed' => 'warning',
                'payment_failed' => 'danger',
                'expired' => 'neutral',
                'cancelled' => 'neutral',
                'refunded' => 'neutral',
            ];
            $statusLabels = [
                'pending_payment' => 'Paiement en attente',
                'paid' => 'Payée',
                'processing' => 'Préparation en cours',
                'activation_awaiting' => 'Activation en cours',
                'delivered' => 'Livrée',
                'failed' => 'Échec de livraison (à traiter)',
                'payment_failed' => 'Paiement échoué',
                'expired' => 'Expirée',
                'cancelled' => 'Annulée',
                'refunded' => 'Remboursée',
            ];
            $tone = $statusTones[$order->status] ?? 'neutral';
            $label = $statusLabels[$order->status] ?? $order->status;
            $unitCost = (int) ceil(($order->unit_cost_usd_cents * $order->quantity * $order->usd_rate) / 100);
            $margin = $order->total_xof - $unitCost;
        @endphp
        <span class="badge badge-{{ $tone }} text-sm py-1 px-3">{{ $label }}</span>
    </div>

    @if($order->status === 'failed')
        <section class="card space-y-4 border-l-4 border-l-accent p-5">
            <div>
                <h2 class="h3">Commande payée non livrée</h2>
                <p class="mt-1 text-sm text-muted">
                    Cause : <strong>{{ $order->failure_code ?? 'inconnue' }}</strong> — {{ $order->failure_message ?? 'sans détail' }}
                </p>
                @if($order->refund_requested_at)
                    <p class="mt-2 text-sm font-semibold text-danger">Le client a demandé un remboursement le {{ $order->refund_requested_at->format('d/m/Y H:i') }}.</p>
                @endif
            </div>

            @if(in_array($order->failure_code, ['too_many_attempts', 'empty_delivery', 'uncertain_outcome', 'manual_hold', 'activation_timeout']))
                <p class="alert alert-warning">Issue incertaine chez le fournisseur : vérifiez cette commande dans le bot Telegram avant toute action. « Relancer » réutilise la même clé d'idempotence (pas de double achat, possible pendant 23 h) ; « Créditer » ne se fait que si rien n'a été livré.</p>
            @endif

            <div class="flex flex-wrap items-start gap-3">
                @if($order->failure_code !== 'amount_mismatch')
                    <form action="{{ route('admin.orders.retry', $order->id) }}" method="POST" onsubmit="return confirm('Relancer l\'achat chez le fournisseur ? Le solde fournisseur sera débité.');">
                        @csrf
                        <button type="submit" class="btn btn-brand btn-sm">
                            Relancer la livraison
                        </button>
                    </form>

                    <form action="{{ route('admin.orders.credit', $order->id) }}" method="POST" onsubmit="return confirm('Créditer {{ number_format($order->total_xof, 0, ',', ' ') }} FCFA sur le solde du client ? Cette action clôt la commande.');">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">
                            Créditer le solde du client
                        </button>
                    </form>
                @endif
            </div>

            @if($order->paid_with === 'gateway')
                <form action="{{ route('admin.orders.refund_gateway', $order->id) }}" method="POST" class="space-y-3 border-t border-line pt-4" onsubmit="return confirm('Marquer comme remboursée auprès de la passerelle ?');">
                    @csrf
                    <p class="text-sm font-semibold">Remboursement chez le prestataire</p>
                    <p class="text-xs leading-relaxed text-muted">Émettez d'abord le remboursement dans le tableau de bord pawaPay (paiement {{ $order->payment_intent_id ?? '—' }}), puis enregistrez-le ici. Cette étape ne déclenche aucun mouvement d'argent.</p>
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" name="confirm" required class="size-4 accent-brand">
                        J'ai émis le remboursement de {{ number_format($order->total_xof, 0, ',', ' ') }} FCFA chez pawaPay
                    </label>
                    <button type="submit" class="btn btn-danger btn-sm">
                        Marquer comme remboursée
                    </button>
                </form>
            @endif
        </section>
    @endif

    @if(in_array($order->status, ['pending_payment', 'expired', 'payment_failed']))
        <form action="{{ route('admin.orders.sync', $order->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-soft btn-sm">
                Revérifier le paiement auprès de pawaPay
            </button>
        </form>
    @endif

    @if(in_array($order->status, ['processing', 'activation_awaiting']))
        <section class="card space-y-3 border-l-4 border-l-accent p-5">
            <h2 class="h3">Commande bloquée ?</h2>
            <p class="text-sm text-muted">Si le fournisseur ne termine jamais cette commande, mettez-la en échec pour pouvoir la créditer, la relancer ou la rembourser. Vérifiez d'abord son état dans le bot Telegram.</p>
            <form action="{{ route('admin.orders.mark_failed', $order->id) }}" method="POST" onsubmit="return confirm('Mettre cette commande en échec ?');">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm">
                    Mettre en échec
                </button>
            </form>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <section class="card px-5 py-4">
            <h2 class="h3 mb-3">Commande</h2>
            <dl class="divide-y divide-line text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Produit</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->product_name }} × {{ $order->quantity }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Montant payé</dt>
                    <dd class="min-w-0 text-right font-semibold text-ink">{{ number_format($order->total_xof, 0, ',', ' ') }} FCFA</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Coût fournisseur</dt>
                    <dd class="min-w-0 text-right font-medium text-ink font-mono text-xs">
                        ${{ number_format(($order->unit_cost_usd_cents * $order->quantity) / 100, 2) }} ≈ {{ number_format($unitCost, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Marge</dt>
                    <dd class="min-w-0 text-right font-semibold text-success">{{ ($margin >= 0 ? '+' : '') . number_format($margin, 0, ',', ' ') }} FCFA</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Taux appliqué</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">1 $ = {{ $order->usd_rate }} FCFA</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Payée avec</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->paid_with === 'balance' ? 'Solde client' : 'pawaPay' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Créée le</dt>
                    <dd class="min-w-0 text-right font-medium text-ink text-xs">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Payée le</dt>
                    <dd class="min-w-0 text-right font-medium text-ink text-xs">{{ $order->paid_at ? \Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i') : '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Livrée le</dt>
                    <dd class="min-w-0 text-right font-medium text-ink text-xs">{{ $order->delivered_at ? \Carbon\Carbon::parse($order->delivered_at)->format('d/m/Y H:i') : '—' }}</dd>
                </div>
                @if($order->refunded_at)
                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                        <dt class="text-muted">Remboursée le</dt>
                        <dd class="min-w-0 text-right font-medium text-ink text-xs">{{ \Carbon\Carbon::parse($order->refunded_at)->format('d/m/Y H:i') }} ({{ $order->refund_method === 'balance' ? 'avoir' : 'prestataire' }})</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="card px-5 py-4">
            <h2 class="h3 mb-3">Client et références</h2>
            <dl class="divide-y divide-line text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">E-mail</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->customer_email ?: ($order->user->email ?? '—') }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Nom</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ trim("{$order->customer_first_name} {$order->customer_last_name}") ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Téléphone</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->customer_phone ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Pays</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->customer_country ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Paiement pawaPay</dt>
                    <dd class="min-w-0 text-right font-mono text-xs text-ink">{{ $order->payment_intent_id ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Commande fournisseur</dt>
                    <dd class="min-w-0 text-right font-mono text-xs text-ink">{{ $order->supplier_order_id ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Statut fournisseur</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->supplier_status ?: '—' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 py-2.5">
                    <dt class="text-muted">Essais de livraison</dt>
                    <dd class="min-w-0 text-right font-medium text-ink">{{ $order->fulfil_attempts }}</dd>
                </div>
            </dl>
        </section>
    </div>

    @if($order->status === 'delivered')
        <section class="card p-5">
            <h2 class="h3">Accès livrés</h2>
            @if($reveal)
                <div class="mt-3">
                    @php
                        $decrypted = null;
                        try {
                            $decrypted = $order->delivery_encrypted ? \App\Services\CryptoService::decrypt($order->delivery_encrypted, $order->id) : null;
                        } catch(\Throwable $e) {
                            $decrypted = null;
                        }
                    @endphp
                    @if($decrypted)
                        <pre class="bg-canvas border border-line rounded-xl p-4 text-xs font-mono whitespace-pre-wrap overflow-x-auto">{{ is_string($decrypted) ? $decrypted : json_encode($decrypted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <p class="alert alert-error">Déchiffrement impossible : vérifiez la clé d'application.</p>
                    @endif
                </div>
            @else
                <p class="hint mb-3">Masqués par défaut. Chaque consultation est inscrite au journal.</p>
                <a href="{{ route('admin.orders.show', ['id' => $order->id, 'acces' => 1]) }}" class="btn btn-soft btn-sm">
                    Afficher les accès
                </a>
            @endif
        </section>
    @endif

    <form action="{{ route('admin.orders.note', $order->id) }}" method="POST" class="card space-y-3 p-5">
        @csrf
        <label for="note" class="h3 block">Note interne</label>
        <textarea id="note" name="note" rows="3" maxlength="2000" class="input">{{ old('note', $order->admin_note) }}</textarea>
        <button type="submit" class="btn btn-soft btn-sm">
            Enregistrer la note
        </button>
    </form>

    <section>
        <h2 class="h3 mb-3">Historique</h2>
        @if($order->events && $order->events->count() > 0)
            <ol class="card divide-y divide-line">
                @foreach($order->events as $event)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3 text-sm">
                        <span class="min-w-0 text-ink">{{ $event->message }}</span>
                        <span class="text-xs whitespace-nowrap text-muted">
                            {{ $event->actor }} · {{ $event->created_at ? $event->created_at->format('d/m/Y H:i') : '' }}
                        </span>
                    </li>
                @endforeach
            </ol>
        @else
            <div class="card p-4 text-sm text-muted">
                Aucun événement enregistré pour cette commande.
            </div>
        @endif
    </section>
</div>
@endsection
