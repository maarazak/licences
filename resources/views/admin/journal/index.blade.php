@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Journal d'audit</h1>
        <p class="text-xs text-slate-500 mt-0.5">Traçabilité des actions sensibles de l'administration</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3.5">Action</th>
                        <th class="px-6 py-3.5">Cible</th>
                        <th class="px-6 py-3.5">Détails</th>
                        <th class="px-6 py-3.5">IP</th>
                        <th class="px-6 py-3.5 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-6 py-4 font-semibold text-slate-900">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-mono bg-slate-100 text-slate-800">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-700">
                                {{ $log->target_type }} ({{ Str::limit($log->target_id, 12) }})
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 font-mono">
                                {{ json_encode($log->meta) }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right text-xs text-slate-500">
                                {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                Aucun événement d'audit enregistré.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
