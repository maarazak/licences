@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders.index') }}" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-100 transition text-slate-500">
                <i data-lucide="arrow-left" class="size-4"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">Commande {{ $order->reference }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">Créée le {{ $order->created_at->format('d/m/Y à H:i') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($order->status === 'pending' || $order->paid_with === 'pawapay')
                <form action="{{ route('admin.orders.sync', $order->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                        Synchroniser pawaPay
                    </button>
                </form>
            @endif

            @if(in_array($order->status, ['paid', 'failed']) && !$order->delivered_at)
                <form action="{{ route('admin.orders.retry', $order->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                        Relancer livraison
                    </button>
                </form>
            @endif

            @if($order->status !== 'refunded')
                <button type="button" onclick="confirmAction({
                    title: 'Rembourser sur le solde client ?',
                    text: 'Le montant de {{ number_format($order->total_xof, 0, ',', ' ') }} FCFA sera immédiatement crédité sur le portefeuille de {{ $order->user->email }}.',
                    confirmText: 'Créditer le solde',
                    formId: 'form-refund-balance'
                })" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Rembourser sur solde
                </button>
                <form id="form-refund-balance" action="{{ route('admin.orders.refund_balance', $order->id) }}" method="POST" class="hidden">
                    @csrf
                </form>

                <button type="button" onclick="confirmAction({
                    title: 'Marquer remboursée sur le moyen de paiement ?',
                    text: 'Indique que le remboursement Mobile Money a été exécuté sur pawaPay.',
                    confirmText: 'Confirmer remboursement',
                    formId: 'form-refund-gateway'
                })" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    Remboursement passerelle
                </button>
                <form id="form-refund-gateway" action="{{ route('admin.orders.refund_gateway', $order->id) }}" method="POST" class="hidden">
                    @csrf
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Informations commande & client -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Détails de la commande</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-xs text-slate-400 block">Produit</span>
                        <strong class="font-semibold text-slate-800">{{ $order->product_name }}</strong>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block">Quantité</span>
                        <strong class="font-semibold text-slate-800">{{ $order->quantity }} licence(s)</strong>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block">Montant total</span>
                        <strong class="font-bold text-teal-700 text-base">{{ number_format($order->total_xof, 0, ',', ' ') }} FCFA</strong>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block">Moyen de paiement</span>
                        <span class="font-medium text-slate-700">{{ $order->paid_with === 'balance' ? 'Solde du compte' : 'Mobile Money (pawaPay)' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block">Statut actuel</span>
                        <span class="font-semibold text-slate-900">{{ strtoupper($order->status) }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block">Date de paiement</span>
                        <span class="font-medium text-slate-700">{{ $order->paid_at ? $order->paid_at->format('d/m/Y H:i') : 'Non payé' }}</span>
                    </div>
                </div>

                @if($order->failure_message)
                    <div class="p-3.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs space-y-1">
                        <strong class="font-semibold block">Dernier statut d'erreur pawaPay / fournisseur :</strong>
                        <p class="font-mono">{{ $order->failure_message }}</p>
                    </div>
                @endif

                @if($order->delivery_encrypted || $order->delivered_at)
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Identifiants de licence</span>
                            <button type="button" onclick="revealCredentials('{{ route('admin.orders.reveal', $order->id) }}')" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-900 text-white font-medium rounded-lg text-xs transition">
                                Afficher les identifiants (Audit)
                            </button>
                        </div>
                        <pre id="creds-output" class="hidden p-3 bg-slate-900 text-emerald-400 font-mono rounded-lg overflow-x-auto whitespace-pre-wrap"></pre>
                    </div>
                @endif

                @if(!$order->delivered_at && $order->status !== 'refunded')
                    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-xs space-y-3">
                        <div>
                            <span class="font-bold text-emerald-900 uppercase tracking-wider text-[11px] block">Livraison manuelle (Fournisseur externe ou secours)</span>
                            <p class="text-emerald-700 mt-0.5">Saisissez les clés, identifiants ou liens d'accès obtenus auprès d'un autre fournisseur. Ils seront chiffrés et immédiatement mis à disposition du client.</p>
                        </div>
                        <form action="{{ route('admin.orders.deliver_manual', $order->id) }}" method="POST" class="space-y-2">
                            @csrf
                            <textarea name="credentials" rows="3" required placeholder="Collez ici les accès (ex: email:password, clé de licence, lien d'invitation)..." class="w-full px-3 py-2 rounded-xl border border-emerald-300 text-xs font-mono focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none transition bg-white"></textarea>
                            <button type="submit" class="px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-xl text-xs transition shadow-sm">
                                Chiffrer & Livrer au client
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Note administrative interne -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Note interne administrative</h2>
                <form action="{{ route('admin.orders.note', $order->id) }}" method="POST" class="space-y-3">
                    @csrf
                    <textarea name="admin_note" rows="3" placeholder="Informations de suivi réservées à l'équipe..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">{{ $order->admin_note }}</textarea>
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium text-xs rounded-xl transition">
                        Enregistrer la note
                    </button>
                </form>
            </div>

            <!-- Historique des événements -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Journal de la commande</h2>
                <div class="space-y-3">
                    @forelse($order->events as $event)
                        <div class="flex items-start gap-3 text-xs border-l-2 border-teal-500 pl-3 py-1">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $event->message }}</p>
                                <p class="text-slate-400 mt-0.5">{{ $event->created_at->format('d/m/Y H:i:s') }} · Par {{ $event->actor ?? 'Système' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Aucun événement enregistré.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Coordonnées client -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Client</h2>
                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block">E-mail</span>
                        <strong class="font-semibold text-slate-800 text-sm">{{ $order->user->email ?? '—' }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Téléphone Mobile Money</span>
                        <span class="font-medium text-slate-800">{{ $order->customer_phone ?? '—' }} ({{ $order->customer_country ?? 'BF' }})</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Solde actuel</span>
                        <strong class="font-semibold text-teal-600 text-sm">{{ number_format($order->user->balance_xof ?? 0, 0, ',', ' ') }} FCFA</strong>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm space-y-3">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">Session pawaPay</h2>
                <div class="space-y-2 text-xs">
                    <div>
                        <span class="text-slate-400 block">Deposit ID</span>
                        <span class="font-mono text-slate-700 truncate block">{{ $order->payment_checkout_id ?? $order->id }}</span>
                    </div>
                    @if($order->payment_url)
                        <div class="pt-2">
                            <a href="{{ $order->payment_url }}" target="_blank" class="block text-center py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-xs transition">
                                Ouvrir page pawaPay ↗
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function revealCredentials(url) {
    if (!confirm('Attention : La consultation des identifiants est journalisée dans l\'audit. Confirmer ?')) return;
    const output = document.getElementById('creds-output');
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            if (data.credentials) {
                output.textContent = data.credentials;
                output.classList.remove('hidden');
            } else {
                alert(data.error || 'Aucun identifiant disponible');
            }
        })
        .catch(err => alert('Erreur lors du déchiffrement'));
}
</script>
@endsection
