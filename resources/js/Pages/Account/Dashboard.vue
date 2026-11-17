<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
  Wallet,
  Package,
  LifeBuoy,
  Bell,
  ArrowRight,
  ExternalLink,
  Plus,
} from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import { ORDER_STATUS_LABELS, ORDER_STATUS_TONES } from '@/Types/enums';

const props = defineProps<{
  recentOrders: any[];
  openTicketsCount: number;
  alertsCount: number;
}>();
</script>

<template>
  <AccountLayout>
    <Head title="Tableau de bord — Espace client" />

    <div class="space-y-8">
      <div>
        <h1 class="h1 text-2xl">Bienvenue dans votre espace</h1>
        <p class="text-xs text-slate-500 mt-1">
          Gérez vos licences numériques, votre portefeuille et vos demandes d'assistance.
        </p>
      </div>

      <!-- Metrics Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <!-- Balance Card -->
        <div class="card p-5 space-y-3 bg-gradient-to-br from-white to-teal-50/50 border-teal-200/60 shadow-card">
          <div class="flex items-center justify-between text-slate-500 text-xs">
            <span class="font-bold uppercase tracking-wider">Solde disponible</span>
            <Wallet class="size-4 text-[#0d5c4a]" />
          </div>
          <p class="font-display text-2xl font-extrabold text-[#0d5c4a]">
            {{ (($page.props.auth?.user as any)?.balance_xof || 0).toLocaleString('fr-FR') }} FCFA
          </p>
          <Link
            href="/compte/solde"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0d5c4a] hover:underline pt-1"
          >
            <Plus class="size-3.5" />
            Recharger mon solde
          </Link>
        </div>

        <!-- Orders Card -->
        <div class="card p-5 space-y-3 shadow-card">
          <div class="flex items-center justify-between text-slate-500 text-xs">
            <span class="font-bold uppercase tracking-wider">Achats récents</span>
            <Package class="size-4 text-slate-400" />
          </div>
          <p class="font-display text-2xl font-extrabold text-slate-900">
            {{ recentOrders.length }}
          </p>
          <Link
            href="/compte/achats"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 pt-1"
          >
            Voir tous mes achats
            <ArrowRight class="size-3.5" />
          </Link>
        </div>

        <!-- Tickets Card -->
        <div class="card p-5 space-y-3 shadow-card">
          <div class="flex items-center justify-between text-slate-500 text-xs">
            <span class="font-bold uppercase tracking-wider">Assistance en cours</span>
            <LifeBuoy class="size-4 text-slate-400" />
          </div>
          <p class="font-display text-2xl font-extrabold text-slate-900">
            {{ openTicketsCount }}
          </p>
          <Link
            href="/compte/assistance"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 pt-1"
          >
            Ouvrir un ticket d'aide
            <ArrowRight class="size-3.5" />
          </Link>
        </div>
      </div>

      <!-- Recent Orders List -->
      <div class="card p-6 space-y-5 shadow-card">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h2 class="h3 text-base">Dernières commandes</h2>
          <Link href="/compte/achats" class="text-xs font-bold text-[#0d5c4a] hover:underline">
            Tout afficher
          </Link>
        </div>

        <div v-if="recentOrders.length" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="text-slate-400 border-b border-slate-100 uppercase tracking-wider font-semibold">
              <tr>
                <th class="pb-3">Référence</th>
                <th class="pb-3">Produit</th>
                <th class="pb-3">Montant</th>
                <th class="pb-3">Statut</th>
                <th class="pb-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-slate-50/70 transition">
                <td class="py-3 font-mono font-bold text-slate-900">{{ order.reference }}</td>
                <td class="py-3 max-w-xs truncate">{{ order.product_name }}</td>
                <td class="py-3 font-bold text-[#0d5c4a]">{{ (order.total_xof || 0).toLocaleString('fr-FR') }} FCFA</td>
                <td class="py-3">
                  <span
                    :class="[
                      'badge text-[11px]',
                      order.status === 'paid' || order.status === 'delivered' ? 'badge-success' : '',
                      order.status === 'pending_payment' ? 'badge-warning' : '',
                      order.status === 'failed' || order.status === 'payment_failed' ? 'badge-danger' : '',
                    ]"
                  >
                    {{ ORDER_STATUS_LABELS[order.status as keyof typeof ORDER_STATUS_LABELS] || order.status }}
                  </span>
                </td>
                <td class="py-3 text-right">
                  <Link
                    :href="`/compte/achats/${order.id}`"
                    class="btn btn-soft btn-sm text-[11px] py-1 inline-flex items-center gap-1"
                  >
                    Voir l'accès
                    <ArrowRight class="size-3" />
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-8 text-xs text-slate-400 space-y-3">
          <p>Vous n'avez pas encore passé de commande.</p>
          <Link href="/boutique" class="btn btn-primary btn-sm inline-flex">
            Découvrir la boutique
          </Link>
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
