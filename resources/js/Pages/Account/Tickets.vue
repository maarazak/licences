<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LifeBuoy, Plus, ArrowRight, MessageSquare } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import { TICKET_STATUS_LABELS } from '@/Types/enums';

const props = defineProps<{
  tickets: any[];
}>();
</script>

<template>
  <AccountLayout>
    <Head title="Assistance & Tickets — Espace client" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="h1 text-2xl">Assistance & Support</h1>
          <p class="text-xs text-slate-500 mt-1">
            Besoin d'aide ou d'un remplacement ? Ouvrez un ticket, notre équipe vous répond en priorité.
          </p>
        </div>

        <Link href="/compte/assistance/nouveau" class="btn btn-primary btn-sm">
          <Plus class="size-4" />
          Nouveau ticket
        </Link>
      </div>

      <div class="card p-6 shadow-card space-y-4">
        <div v-if="tickets.length" class="divide-y divide-slate-100">
          <Link
            v-for="ticket in tickets"
            :key="ticket.id"
            :href="`/compte/assistance/${ticket.id}`"
            class="py-4 flex items-center justify-between gap-4 hover:bg-slate-50/70 p-3 rounded-xl transition group"
          >
            <div class="space-y-1 min-w-0">
              <h3 class="font-bold text-slate-900 group-hover:text-[#0d5c4a] transition truncate text-sm">
                {{ ticket.subject }}
              </h3>
              <p class="text-xs text-slate-400">
                Ouvert le {{ new Date(ticket.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
              </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
              <span
                :class="[
                  'badge text-xs',
                  ticket.status === 'open' ? 'badge-warning' : '',
                  ticket.status === 'answered' ? 'badge-success' : '',
                  ticket.status === 'closed' ? 'badge-neutral' : '',
                ]"
              >
                {{ TICKET_STATUS_LABELS[ticket.status as keyof typeof TICKET_STATUS_LABELS] || ticket.status }}
              </span>
              <ArrowRight class="size-4 text-slate-300 group-hover:text-slate-600 transition" />
            </div>
          </Link>
        </div>

        <div v-else class="text-center py-12 text-xs text-slate-400 space-y-3">
          <LifeBuoy class="size-10 mx-auto text-slate-300" />
          <p>Vous n'avez aucun ticket de support ouvert.</p>
          <Link href="/compte/assistance/nouveau" class="btn btn-soft btn-sm inline-flex">
            Ouvrir une demande
          </Link>
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
