@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Assistance</h1>
        </div>
    </div>

    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrer par statut">
        <a href="{{ route('admin.tickets.index') }}" class="btn btn-sm {{ empty($status) ? 'btn-brand' : 'btn-soft' }}">
            Toutes
        </a>
        @foreach($statuses as $key => $label)
            <a href="{{ route('admin.tickets.index', ['statut' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-brand' : 'btn-soft' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tickets->count() > 0)
        <ul class="space-y-2">
            @foreach($tickets as $t)
                @php
                    $tones = [
                        'open' => 'warning',
                        'answered' => 'info',
                        'closed' => 'neutral',
                    ];
                    $labels = [
                        'open' => 'En attente de réponse',
                        'answered' => 'Répondu',
                        'closed' => 'Clôturé',
                    ];
                    $tone = $tones[$t->status] ?? 'neutral';
                    $lbl = $labels[$t->status] ?? $t->status;
                @endphp
                <li>
                    <a href="{{ route('admin.tickets.show', $t->id) }}" class="card flex flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:border-ink block">
                        <div class="min-w-0">
                            <p class="h3 truncate">{{ $t->subject }}</p>
                            <p class="text-xs text-muted mt-0.5">
                                {{ $t->reference }} · {{ $t->user->email ?? '—' }} · {{ $t->updated_at ? $t->updated_at->format('d/m/Y H:i') : '' }}
                            </p>
                        </div>
                        <span class="badge badge-{{ $tone }}">{{ $lbl }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if($tickets->hasPages())
            <div class="mt-4">
                {{ $tickets->links() }}
            </div>
        @endif
    @else
        <div class="card px-6 py-10 text-center">
            <p class="h3">Aucune demande</p>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                Aucune demande d'assistance ne correspond à ce filtre.
            </p>
        </div>
    @endif
</div>
@endsection
