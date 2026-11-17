@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Commandes</h1>
        </div>
    </div>

    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrer par statut">
        @foreach($filters as $key => $label)
            <a href="{{ $key === 'toutes' ? route('admin.orders.index') : route('admin.orders.index', ['filtre' => $key]) }}" class="btn btn-sm {{ $filtre === $key ? 'btn-brand' : 'btn-soft' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-end gap-3">
        @if($filtre !== 'toutes')
            <input type="hidden" name="filtre" value="{{ $filtre }}">
        @endif
        <div class="min-w-0 flex-1 basis-60">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="Référence, e-mail ou produit" class="input">
        </div>
        <button type="submit" class="btn btn-soft">
            Rechercher
        </button>
        @if($q !== '')
            <a href="{{ $filtre === 'toutes' ? route('admin.orders.index') : route('admin.orders.index', ['filtre' => $filtre]) }}" class="btn btn-soft text-muted">
                Effacer
            </a>
        @endif
    </form>

    @if($orders->count() > 0)
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Commandes</caption>
                <thead>
                    <tr>
                        <th scope="col">Référence</th>
                        <th scope="col">Client</th>
                        <th scope="col">Produit</th>
                        <th scope="col" class="text-right">Montant</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="font-medium hover:underline text-ink">
                                    {{ $order->reference }}
                                </a>
                            </td>
                            <td>
                                <span class="font-medium text-ink block">{{ $order->customer_email ?: ($order->user->email ?? '—') }}</span>
                                @if($order->customer_phone)
                                    <span class="block text-xs text-muted">{{ $order->customer_phone }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-ink">{{ $order->product_name }}</span>
                                @if($order->quantity > 1)
                                    <span class="text-xs text-muted">× {{ $order->quantity }}</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap font-semibold">
                                {{ number_format($order->total_xof, 0, ',', ' ') }} FCFA
                            </td>
                            <td>
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
                                @endphp
                                <span class="badge badge-{{ $tone }}">{{ $label }}</span>
                            </td>
                            <td class="whitespace-nowrap text-muted text-xs">
                                {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        @endif
    @else
        <div class="card px-6 py-10 text-center">
            <p class="h3">{{ $q ? 'Aucune commande ne correspond' : 'Aucune commande' }}</p>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                {{ $q ? 'Modifiez la recherche ou le filtre sélectionné.' : 'Les commandes de la boutique apparaîtront ici.' }}
            </p>
        </div>
    @endif
</div>
@endsection
