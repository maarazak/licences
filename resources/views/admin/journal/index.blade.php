@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="h1">Journal</h1>
            <p class="lead mt-2 max-w-2xl">Les 300 dernières actions effectuées dans le backoffice.</p>
        </div>
    </div>

    @if($logs->count() > 0)
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Journal des actions</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Administrateur</th>
                        <th scope="col">Action</th>
                        <th scope="col">Détails</th>
                        <th scope="col">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-muted text-xs">
                                {{ $log->created_at ? $log->created_at->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="max-w-48 truncate text-ink font-medium">
                                {{ $log->admin_email ?: 'Système' }}
                            </td>
                            <td class="font-mono text-xs text-ink font-semibold">
                                {{ $log->action }}
                            </td>
                            <td class="text-muted [overflow-wrap:anywhere] text-xs">
                                @php
                                    $details = is_array($log->details) ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : $log->details;
                                    $parts = array_filter([$log->target, $details]);
                                @endphp
                                {{ count($parts) ? implode(' — ', $parts) : '—' }}
                            </td>
                            <td class="font-mono text-xs text-muted whitespace-nowrap">
                                {{ $log->ip ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    @else
        <div class="card px-6 py-10 text-center">
            <p class="h3">Journal vide</p>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                Les actions du backoffice apparaîtront ici.
            </p>
        </div>
    @endif
</div>
@endsection
